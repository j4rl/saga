document.addEventListener('DOMContentLoaded', () => {
  const cookieBanner = document.querySelector('[data-cookie-banner]');
  const cookieAcceptButton = document.querySelector('[data-cookie-accept]');
  if (cookieBanner && cookieAcceptButton) {
    cookieAcceptButton.addEventListener('click', () => {
      const secureCookie = window.location.protocol === 'https:' ? '; Secure' : '';
      document.cookie = `saga_cookie_consent=accepted; Max-Age=315360000; Path=/; SameSite=Lax${secureCookie}`;
      cookieBanner.hidden = true;
    });
  }

  const themePicker = document.querySelector('[data-theme-picker]');
  const themeButtons = themePicker ? Array.from(themePicker.querySelectorAll('[data-theme-option]')) : [];
  if (themeButtons.length > 0) {
    const allowedThemes = ['light', 'auto', 'dark'];
    const cookieMatch = document.cookie.match(/(?:^|;\s*)saga_theme_mode=([^;]+)/);
    let activeTheme = 'auto';

    try {
      const storedTheme = window.localStorage.getItem('saga.themeMode');
      if (allowedThemes.includes(storedTheme)) {
        activeTheme = storedTheme;
      } else if (cookieMatch && allowedThemes.includes(cookieMatch[1])) {
        activeTheme = cookieMatch[1];
      }
    } catch (error) {
      if (cookieMatch && allowedThemes.includes(cookieMatch[1])) {
        activeTheme = cookieMatch[1];
      }
    }

    const applyTheme = (theme) => {
      activeTheme = allowedThemes.includes(theme) ? theme : 'auto';
      document.documentElement.dataset.theme = activeTheme;
      themeButtons.forEach((button) => {
        const selected = button.dataset.themeOption === activeTheme;
        button.setAttribute('aria-checked', selected ? 'true' : 'false');
        button.classList.toggle('active', selected);
      });
      const secureCookie = window.location.protocol === 'https:' ? '; Secure' : '';
      document.cookie = `saga_theme_mode=${activeTheme}; Max-Age=31536000; Path=/; SameSite=Lax${secureCookie}`;

      try {
        window.localStorage.setItem('saga.themeMode', activeTheme);
      } catch (error) {
      }
    };

    themeButtons.forEach((button) => {
      button.addEventListener('click', () => {
        applyTheme(button.dataset.themeOption);
      });
    });
    applyTheme(activeTheme);
  }

  document.querySelectorAll('textarea[data-counter]').forEach((textarea) => {
    const target = document.getElementById(textarea.dataset.counter);
    const update = () => {
      if (target) {
        target.textContent = String(textarea.value.length);
      }
    };

    textarea.addEventListener('input', update);
    update();
  });

  const fileInput = document.querySelector('input[type="file"][name="pdf_file"]');
  if (fileInput) {
    fileInput.addEventListener('change', () => {
      const file = fileInput.files[0];
      if (!file) {
        return;
      }

      const maxBytes = Number(fileInput.dataset.maxBytes || 15728640);
      const isPdfName = file.name.toLowerCase().endsWith('.pdf');
      const isPdfType = file.type === 'application/pdf' || file.type === '';

      if (!isPdfName || !isPdfType) {
        fileInput.setCustomValidity('Välj en PDF-fil.');
      } else if (file.size > maxBytes) {
        fileInput.setCustomValidity('PDF-filen får vara högst 15 MB.');
      } else {
        fileInput.setCustomValidity('');
      }

      fileInput.reportValidity();
    });
  }

  const publicCheckbox = document.querySelector('[data-public-requires-submission]');
  const submittedCheckbox = document.querySelector('[data-submission-toggle]');
  if (publicCheckbox && submittedCheckbox) {
    const validatePublication = () => {
      if (publicCheckbox.checked && !submittedCheckbox.checked) {
        publicCheckbox.setCustomValidity('Arbetet kan bara göras publikt när slutlig inlämning är ikryssad.');
      } else {
        publicCheckbox.setCustomValidity('');
      }
    };

    publicCheckbox.addEventListener('change', validatePublication);
    submittedCheckbox.addEventListener('change', validatePublication);
    validatePublication();
  }

  const categoryInput = document.querySelector('[data-category-autocomplete]');
  if (categoryInput) {
    const list = document.getElementById(categoryInput.getAttribute('list'));
    const options = list ? Array.from(list.options).map((option) => option.value).filter(Boolean) : [];
    const suggestions = document.createElement('div');
    suggestions.className = 'autocomplete-list';
    suggestions.hidden = true;
    categoryInput.insertAdjacentElement('afterend', suggestions);

    const hideSuggestions = () => {
      suggestions.hidden = true;
      suggestions.innerHTML = '';
    };

    const choose = (value) => {
      categoryInput.value = value;
      hideSuggestions();
      categoryInput.focus();
    };

    const renderSuggestions = () => {
      const query = categoryInput.value.trim().toLowerCase();
      if (!query) {
        hideSuggestions();
        return;
      }

      const matches = options
        .filter((value) => value.toLowerCase().includes(query))
        .slice(0, 8);

      if (matches.length === 0) {
        hideSuggestions();
        return;
      }

      suggestions.innerHTML = '';
      matches.forEach((value) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = value;
        button.addEventListener('mousedown', (event) => {
          event.preventDefault();
          choose(value);
        });
        suggestions.appendChild(button);
      });
      suggestions.hidden = false;
    };

    categoryInput.addEventListener('input', renderSuggestions);
    categoryInput.addEventListener('focus', renderSuggestions);
    categoryInput.addEventListener('blur', () => {
      window.setTimeout(hideSuggestions, 120);
    });
  }

  const themePreview = document.querySelector('[data-theme-preview]');
  const themeColorInputs = document.querySelectorAll('[data-theme-color]');
  const customThemeToggle = document.querySelector('[data-theme-custom-toggle]');
  if (themePreview && themeColorInputs.length > 0) {
    const updateThemePreview = () => {
      const enabled = !customThemeToggle || customThemeToggle.checked;
      themeColorInputs.forEach((input) => {
        input.disabled = !enabled;
        input.closest('.field')?.classList.toggle('muted-control', !enabled);
        if (enabled) {
          themePreview.style.setProperty(input.dataset.themeColor, input.value);
        } else {
          themePreview.style.removeProperty(input.dataset.themeColor);
        }
      });
    };

    themeColorInputs.forEach((input) => {
      input.addEventListener('input', updateThemePreview);
    });
    customThemeToggle?.addEventListener('change', updateThemePreview);
    updateThemePreview();
  }
});


