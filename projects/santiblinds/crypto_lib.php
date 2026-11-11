<?php
// ============================================================
//  Santi Blinds — field-level encryption library
//
//  * AES-256-GCM (authenticated encryption), random 96-bit IV per value
//  * The column name ("table.column") is bound in as Additional Authenticated
//    Data, so a ciphertext cannot be moved to a different column and still open
//  * Stored format:   ENC1.<keyId>.<base64( iv | tag | ciphertext )>
//  * Keys live OUTSIDE the database and web root (see ENC_KEY_FILE in db_config.php,
//    or the SANTI_ENC_KEYS environment variable). Sub-keys are derived with HKDF.
//  * blindIndex(): keyed HMAC-SHA256 so we can still look a record up by e-mail
//    (login, duplicate check) without storing the e-mail in readable form.
//
//  Loaded automatically by db_config.php. Blocked from browsers by .htaccess.
// ============================================================

const ENC_PREFIX  = 'ENC1';
const ENC_CIPHER  = 'aes-256-gcm';
const ENC_IV_LEN  = 12;
const ENC_TAG_LEN = 16;

// Context ("table.column") maps, keyed by the column name as it appears in query results.
const ENC_MAP_CUSTOMER = [
    'fname'    => 'customers.fname',
    'lname'    => 'customers.lname',
    'email'    => 'customers.email',
    'phone'    => 'customers.phone',
    'location' => 'customers.location',
];
const ENC_MAP_ACTIVITY = [
    'email'      => 'customer_activity.email',
    'details'    => 'customer_activity.details',
    'ip_address' => 'customer_activity.ip_address',
];
// Admin order-detail query aliases (see admin/api/orders.php).
const ENC_MAP_ORDER_DETAIL = [
    'specifications' => 'orders.specifications',
    'delivery_address' => 'order_details.delivery_address',
    'order_contact'  => 'order_details.contact_no',
    'order_notes'    => 'order_details.notes',
];
const ENC_MAP_CUSTOMIZATION = ['notes' => 'customizations.notes'];

// ── Key ring ────────────────────────────────────────────────

/**
 * Key file / env format: one "keyId:base64(32 random bytes)" entry per line.
 * The FIRST entry is the current key (used for new writes); older entries are
 * kept only so existing data stays readable until it has been re-encrypted.
 */
function encKeyring(bool $reload = false): array {
    static $ring = null;
    if ($reload) $ring = null;          // used by tools/encryption_demo.php to switch keys
    if ($ring !== null) return $ring;

    $source = getenv('SANTI_ENC_KEYS');
    if ($source === false || trim($source) === '') {
        $file = defined('ENC_KEY_FILE') ? ENC_KEY_FILE : '';
        if ($file === '' || !is_readable($file)) {
            throw new RuntimeException('Encryption key not found. Run "php tools/generate_key.php" and check ENC_KEY_FILE in db_config.php.');
        }
        $source = (string)file_get_contents($file);
    }

    $built = ['current' => null, 'keys' => []];
    foreach (preg_split('/[\s,;]+/', trim($source), -1, PREG_SPLIT_NO_EMPTY) as $entry) {
        $p = explode(':', $entry, 2);
        if (count($p) !== 2 || !preg_match('/^[A-Za-z0-9_-]{1,16}$/', $p[0])) {
            throw new RuntimeException('Malformed encryption key entry.');
        }
        $master = base64_decode($p[1], true);
        if ($master === false || strlen($master) !== 32) {
            throw new RuntimeException('Encryption key must be 32 random bytes, base64-encoded.');
        }
        $built['keys'][$p[0]] = [
            'enc'  => hash_hkdf('sha256', $master, 32, 'santiblinds/enc/v1'),
            'bidx' => hash_hkdf('sha256', $master, 32, 'santiblinds/bidx/v1'),
        ];
        if ($built['current'] === null) $built['current'] = $p[0];
    }
    if ($built['current'] === null) throw new RuntimeException('Encryption key file is empty.');

    return $ring = $built;
}

// ── Encrypt / decrypt a single value ────────────────────────

function isEncrypted(?string $v): bool {
    return $v !== null && strncmp($v, ENC_PREFIX . '.', strlen(ENC_PREFIX) + 1) === 0;
}

function encKidOf(string $stored): string {
    $parts = explode('.', $stored, 3);
    return $parts[1] ?? '';
}

/** Encrypt a value. NULL and '' are stored as-is (nothing to protect). */
function encField(?string $plain, string $ctx): ?string {
    if ($plain === null || $plain === '') return $plain;

    $ring = encKeyring();
    $kid  = $ring['current'];
    $iv   = random_bytes(ENC_IV_LEN);
    $tag  = '';
    $ct   = openssl_encrypt($plain, ENC_CIPHER, $ring['keys'][$kid]['enc'], OPENSSL_RAW_DATA, $iv, $tag, $ctx, ENC_TAG_LEN);
    if ($ct === false || strlen($tag) !== ENC_TAG_LEN) {
        throw new RuntimeException('Encryption failed.');
    }
    return ENC_PREFIX . '.' . $kid . '.' . base64_encode($iv . $tag . $ct);
}

/**
 * Decrypt a value. Throws if the key is wrong, the column context is wrong, or the
 * data was altered. Values not starting with "ENC1." are treated as legacy plaintext
 * (rows not yet migrated) unless ENC_ALLOW_LEGACY_PLAINTEXT is false.
 */
function decField(?string $stored, string $ctx): ?string {
    if ($stored === null || $stored === '') return $stored;

    if (!isEncrypted($stored)) {
        if (defined('ENC_ALLOW_LEGACY_PLAINTEXT') && ENC_ALLOW_LEGACY_PLAINTEXT === false) {
            throw new RuntimeException("Unencrypted value found in {$ctx}.");
        }
        return $stored;
    }

    $parts = explode('.', $stored, 3);
    if (count($parts) !== 3) throw new RuntimeException('Malformed ciphertext.');
    [, $kid, $blob] = $parts;

    $ring = encKeyring();
    if (!isset($ring['keys'][$kid])) throw new RuntimeException("Unknown encryption key id '{$kid}'.");

    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) <= ENC_IV_LEN + ENC_TAG_LEN) throw new RuntimeException('Malformed ciphertext.');

    $iv  = substr($raw, 0, ENC_IV_LEN);
    $tag = substr($raw, ENC_IV_LEN, ENC_TAG_LEN);
    $ct  = substr($raw, ENC_IV_LEN + ENC_TAG_LEN);

    $plain = openssl_decrypt($ct, ENC_CIPHER, $ring['keys'][$kid]['enc'], OPENSSL_RAW_DATA, $iv, $tag, $ctx);
    if ($plain === false) {
        throw new RuntimeException("Decryption failed for {$ctx} (wrong key, wrong column, or tampered data).");
    }
    return $plain;
}

/** Deterministic keyed hash for equality lookups (e-mail). Case/whitespace-insensitive. */
function blindIndex(?string $value): ?string {
    if ($value === null) return null;
    $v = mb_strtolower(trim($value), 'UTF-8');
    if ($v === '') return null;
    $ring = encKeyring();
    return hash_hmac('sha256', $v, $ring['keys'][$ring['current']]['bidx']);
}

// ── Row helpers ─────────────────────────────────────────────

function encRow(array $row, array $map): array {
    foreach ($map as $field => $ctx) {
        if (array_key_exists($field, $row)) {
            $row[$field] = encField($row[$field] === null ? null : (string)$row[$field], $ctx);
        }
    }
    return $row;
}

function decRow(array $row, array $map): array {
    foreach ($map as $field => $ctx) {
        if (array_key_exists($field, $row)) {
            $row[$field] = decField($row[$field] === null ? null : (string)$row[$field], $ctx);
        }
    }
    return $row;
}

function decRows(array $rows, array $map): array {
    foreach ($rows as $i => $r) $rows[$i] = decRow($r, $map);
    return $rows;
}

/**
 * Case-insensitive "contains" across several fields of an (already decrypted) row.
 * Replaces SQL LIKE on columns that are now encrypted.
 */
function rowMatches(array $row, array $fields, string $needle): bool {
    foreach ($fields as $f) {
        if (isset($row[$f]) && mb_stripos((string)$row[$f], $needle) !== false) return true;
    }
    return false;
}

/** The ?search= value as a trimmed string ('' when absent or not a string). */
function searchTerm(): string {
    return isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : '';
}
