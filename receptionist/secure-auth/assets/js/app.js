document.addEventListener('DOMContentLoaded', () => {
  const forms = document.querySelectorAll('[data-validate]');

  forms.forEach((form) => {
    form.addEventListener('submit', (event) => {
      let valid = true;

      form.querySelectorAll('input[required]').forEach((input) => {
        input.classList.remove('input-error');

        if (!input.value.trim()) {
          input.classList.add('input-error');
          valid = false;
        }

        if (input.type === 'email' && input.value.trim()) {
          const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (!emailPattern.test(input.value.trim())) {
            input.classList.add('input-error');
            valid = false;
          }
        }
      });

      const passwordField = form.querySelector('[data-password-strength]');
      if (passwordField && passwordField.value) {
        const password = passwordField.value;
        const strong =
          password.length >= 12 &&
          /[A-Z]/.test(password) &&
          /[a-z]/.test(password) &&
          /\d/.test(password) &&
          /[^A-Za-z0-9]/.test(password);

        if (!strong) {
          passwordField.classList.add('input-error');
          valid = false;
        }
      }

      if (!valid) {
        event.preventDefault();
      }
    });
  });
});

