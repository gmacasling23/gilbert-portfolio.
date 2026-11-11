/* ============================================
   PRODUCTS DATA - AbiYarn
   ============================================
   HOW TO ADD YOUR PRODUCTS:
   ─────────────────────────────────────────────
   Each product is an object inside the PRODUCTS
   array below. Copy one of the template blocks
   and fill in your own details.

   FIELDS EXPLAINED:
   ┌─────────────┬──────────────────────────────────────────────────────┐
   │ id          │ A unique number. Keep counting up: 1, 2, 3 …        │
   │ name        │ The product name shown on cards and in the cart.     │
   │ category    │ Must be one of: "bouquets" "flowers"                 │
   │             │                 "bags"     "accessories"             │
   │             │ (must match exactly — lowercase, no spaces)          │
   │ price       │ Number only, no ₱ sign. e.g.  650  or  1850         │
   │ description │ Short sentence shown under the product name.         │
   │ image       │ Path to your photo. Put images in assets/products/   │
   │             │ then write:  "assets/products/your-photo.jpg"        │
   └─────────────┴──────────────────────────────────────────────────────┘

   IMAGE TIPS:
   • Square photos look best (1:1 ratio), e.g. 800×800 px
   • Accepted formats: .jpg  .jpeg  .png  .webp
   • Keep file names short with no spaces:
       ✅  pastel-bouquet.jpg
       ✅  sunflower-tote.png
       ❌  My Photo (1) FINAL.JPG

   CATEGORY OPTIONS (for the filter buttons on the Menu page):
       "bouquets"     → Bouquets filter
       "flowers"      → Flowers filter
       "bags"         → Bags filter
       "accessories"  → Accessories filter

   ADDING A NEW PRODUCT — quick steps:
   1. Copy the block between the dashed lines below.
   2. Paste it after the last product (before the ];).
   3. Change the id to the next number.
   4. Fill in name, category, price, description, image.
   5. Save the file. Done!

   ─────────────────────────────────────────────
   TEMPLATE (copy from here):
   ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─

   {
     id: 0,
     name: "Your Product Name",
     category: "bouquets",
     price: 000,
     description: "A short description of your product goes here.",
     image: "assets/products/your-photo.jpg"
   },

   ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─
   ============================================ */

const PRODUCTS = [

  // ── PRODUCT 1 ──────────────────────────────
  {
    id: 1,
    name: "Cherry Key Chain",
    category: "accessories",         // bouquets | flowers | bags | accessories
    price: 70,                   
    description: "A cute handmade crochet cherry keychain designed to add a playful and colorful charm to your everyday essentials..",
    image: "assets/products/cherry1_key.jpg"
  },

  // ── PRODUCT 2 ──────────────────────────────
  {
    id: 2,
    name: "Chopper Key Chain",
    category: "accessories",
    price: 70,
    description: "An anime-inspired crochet keychain featuring an adorable handcrafted design full of personality and charm.",
    image: "assets/products/chopper_key.jpg"
  },

  // ── PRODUCT 3 ──────────────────────────────
  {
    id: 3,
    name: "Cookie Key Chain",
    category: "accessories",
    price: 40,
    description: "A delightful cA handmade crochet cookie accessory that brings a sweet and playful vibe to any collection.",
    image: "assets/products/cookie1_key.jpg"
  },

  // ── PRODUCT 4 ──────────────────────────────
  {
    id: 4,
    name: "Strawhat Luffy Key Chain",
    category: "accessories",
    price: 70,
    description: "A crochet anime-inspired keychain created for fans who love adventurous and iconic characters.",
    image: "assets/products/luffy_key.jpg"
  },

  {
    id: 5,
    name: "Flower Book Mark",
    category: "flowers",
    price: 50,
    description: "A delicate crochet flower bookmark designed to make every reading experience more elegant and creative.",
    image: "assets/products/flower_bookmark.jpg"
  },

  {
    id: 6,
    name: "Lily Bouquet",
    category: "bouquets",
    price: 200,
    description: "A beautifully handcrafted crochet lily bouquet that captures the timeless beauty of flowers that never fade.",
    image: "assets/products/lily_bouquet.jpg"
  },

  {
    id: 7,
    name: "Mini Pouch",
    category: "bags",
    price: 50,
    description: "A cute handmade crochet mini pouch designed to add a playful and colorful charm to your everyday essentials..",
    image: "assets/products/pouch.jpg"
  },

  {
    id: 8,
    name: "Super Pouch ",
    category: "bags",
    price: 100,
    description: "A stylish handmade crochet pouch designed for storing small essentials with charm and functionality.",
    image: "assets/products/pouch2.jpg"
  },

  {
    id: 9,
    name: "3 pcs Rose Bouquet with LED lights",
    category: "bouquets",
    price: 220,
    description: "Four beautifully handcrafted crochet rose bouquet that captures the timeless beauty of flowers that never fade.",
    image: "assets/products/rose_bouquet.jpg"
  },

  {
    id: 10,
    name: "Rose Bouquet with Ribbon",
    category: "bouquets",
    price: 350,
    description: "Three romantic crochet rose bouquet carefully handmade to symbolize everlasting love and beauty.",
    image: "assets/products/rose_bouquet(1).jpg"
  },

  {
    id: 11,
    name: "Summer Ready Sling Bag",
    category: "bags",
    price: 400,
    description: "A stylish handmade crochet sling bag designed for storing small essentials with charm and functionality.",
    image: "assets/products/slingbag2.jpg"
  },

  {
    id: 12,
    name: "Sunflower Bouquet",
    category: "bouquets",
    price: 160,
    description: "A beautiful crochet sunflower bouquet that brings a cheerful and vibrant touch to any space, like having a sun in your bag.",
    image: "assets/products/sunflower_bouquet.jpg"
  },

  {
    id: 13,
    name: "Sunflower Key Chain",
    category: "accessories",
    price: 50,
    description: "A delightful crochet sunflower keychain that brings a cheerful and vibrant touch to any collection.",
    image: "assets/products/sunflower_key.jpg"
  },

  {
    id: 14,
    name: "Tumbler Bag Pouch",
    category: "bags",
    price: 200,
    description: "A stylish handmade crochet pouch designed for storing Tumblers with charm and functionality.",
    image: "assets/products/thumbler.jpeg"
  },

  {
    id: 15,
    name: "Wedding Bouquet",
    category: "bouquets",
    price: 1500,
    description: "A beautiful crochet wedding bouquet designed to add a touch of elegance and romance to any special occasion.",
    image: "assets/products/wedding_bouquet.jpeg"
  }
];

/* ============================================
   PRICE FORMATTER — do not edit this part
   ============================================ */
function formatPrice(amount) {
  return `₱${amount.toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })}`;
}