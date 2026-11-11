'use strict';

(function () {
  function nationalDigits(value) {
    let digits = value.replace(/\D/g, '');
    if (digits.startsWith('0063')) digits = digits.slice(4);
    else if (digits.startsWith('63')) digits = digits.slice(2);
    if (digits.startsWith('0')) digits = digits.slice(1);
    return digits;
  }

  function formatPhilippineMobile(value) {
    const digits = nationalDigits(value).slice(0, 10);
    const groups = [digits.slice(0, 3), digits.slice(3, 6), digits.slice(6, 10)]
      .filter(Boolean);
    return groups.join(' ');
  }

  function isValidPhilippineMobile(value) {
    const digits = nationalDigits(value);
    return digits.length === 10 && /^9\d{9}$/.test(digits);
  }

  function toInternational(value) {
    return isValidPhilippineMobile(value) ? '+63 ' + formatPhilippineMobile(value) : '';
  }

  document.querySelectorAll('input[type="tel"]').forEach(input => {
    input.addEventListener('input', () => {
      const beforeCursor = input.value.slice(0, input.selectionStart || 0);
      const cursorDigits = nationalDigits(beforeCursor).length;
      input.value = formatPhilippineMobile(input.value);

      let position = 0;
      let digitsSeen = 0;
      while (position < input.value.length && digitsSeen < cursorDigits) {
        if (/\d/.test(input.value[position])) digitsSeen++;
        position++;
      }
      input.setSelectionRange(position, position);
    });

    input.addEventListener('blur', () => {
      if (input.value) input.value = formatPhilippineMobile(input.value);
    });
  });

  window.SantiPhone = {
    format: formatPhilippineMobile,
    isValid: isValidPhilippineMobile,
    toInternational,
  };
})();
