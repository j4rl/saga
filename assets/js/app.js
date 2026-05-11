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
  const publicationConsent = document.querySelector('[data-publication-consent]');
  const publicationConsentCheckbox = publicationConsent ? publicationConsent.querySelector('input[type="checkbox"]') : null;
  if (publicCheckbox && submittedCheckbox) {
    const validatePublication = () => {
      if (publicCheckbox.checked && !submittedCheckbox.checked) {
        publicCheckbox.setCustomValidity('Arbetet kan bara göras publikt när slutlig inlämning är ikryssad.');
      } else {
        publicCheckbox.setCustomValidity('');
      }

      if (publicationConsent && publicationConsentCheckbox) {
        publicationConsent.hidden = !publicCheckbox.checked;
        publicationConsentCheckbox.required = publicCheckbox.checked;
        if (!publicCheckbox.checked) {
          publicationConsentCheckbox.checked = false;
        }
      }
    };

    publicCheckbox.addEventListener('change', validatePublication);
    submittedCheckbox.addEventListener('change', validatePublication);
    validatePublication();
  }

  const supervisorSelect = document.querySelector('[data-supervisor-select]');
  const manualSupervisorField = document.querySelector('[data-manual-supervisor-field]');
  if (supervisorSelect && manualSupervisorField) {
    const manualSupervisorInput = manualSupervisorField.querySelector('input');
    const updateSupervisorMode = () => {
      const isManual = supervisorSelect.value === '0';
      manualSupervisorField.hidden = !isManual;
      if (manualSupervisorInput) {
        manualSupervisorInput.required = isManual;
        manualSupervisorInput.disabled = supervisorSelect.disabled || !isManual;
      }
    };

    supervisorSelect.addEventListener('change', updateSupervisorMode);
    updateSupervisorMode();
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
  const themeColorInputs = document.querySelectorAll('[data-theme-seed]');
  const customThemeToggle = document.querySelector('[data-theme-custom-toggle]');
  if (themePreview && themeColorInputs.length > 0) {
    const hexToRgb = (color) => {
      const value = color.replace('#', '');
      return [
        parseInt(value.slice(0, 2), 16),
        parseInt(value.slice(2, 4), 16),
        parseInt(value.slice(4, 6), 16),
      ];
    };

    const rgbToHex = (rgb) => `#${rgb.map((channel) => {
      const value = Math.max(0, Math.min(255, Math.round(channel)));
      return value.toString(16).padStart(2, '0');
    }).join('')}`;

    const mixHex = (color, target, targetWeight) => {
      const sourceRgb = hexToRgb(color);
      const targetRgb = hexToRgb(target);
      const sourceWeight = 1 - targetWeight;
      return rgbToHex([
        (sourceRgb[0] * sourceWeight) + (targetRgb[0] * targetWeight),
        (sourceRgb[1] * sourceWeight) + (targetRgb[1] * targetWeight),
        (sourceRgb[2] * sourceWeight) + (targetRgb[2] * targetWeight),
      ]);
    };

    const luminance = (color) => {
      const channels = hexToRgb(color).map((channel) => {
        const value = channel / 255;
        return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
      });
      return (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);
    };

    const contrastRatio = (foreground, background) => {
      const lighter = Math.max(luminance(foreground), luminance(background));
      const darker = Math.min(luminance(foreground), luminance(background));
      return (lighter + 0.05) / (darker + 0.05);
    };

    const contrastsWithAll = (color, backgrounds, minimumRatio) => (
      backgrounds.every((background) => contrastRatio(color, background) >= minimumRatio)
    );

    const adjustForContrast = (color, backgrounds, minimumRatio = 4.5) => {
      if (contrastsWithAll(color, backgrounds, minimumRatio)) {
        return color;
      }

      for (const target of ['#ffffff', '#000000']) {
        for (let step = 1; step <= 20; step += 1) {
          const candidate = mixHex(color, target, step * 0.05);
          if (contrastsWithAll(candidate, backgrounds, minimumRatio)) {
            return candidate;
          }
        }
      }

      return contrastsWithAll('#ffffff', backgrounds, minimumRatio) ? '#ffffff' : '#000000';
    };

    const readableTextColor = (background) => (
      contrastRatio('#ffffff', background) >= contrastRatio('#0d211a', background) ? '#ffffff' : '#0d211a'
    );

    const supportVars = (primary, surface, text) => ({
      '--primary-strong': `color-mix(in srgb, ${primary} 88%, ${text})`,
      '--on-primary': readableTextColor(primary),
      '--surface-strong': `color-mix(in srgb, ${surface} 92%, ${text})`,
      '--line': `color-mix(in srgb, ${surface} 78%, ${text})`,
      '--muted': `color-mix(in srgb, ${text} 68%, ${surface})`,
    });

    const deriveTheme = (primary, secondary) => {
      const bg = mixHex(primary, '#ffffff', 0.96);
      const surface = mixHex(secondary, '#ffffff', 0.985);
      const text = adjustForContrast(mixHex(primary, '#111827', 0.86), [bg, surface], 4.5);
      const link = adjustForContrast(secondary, [bg, surface], 4.5);

      const darkBg = mixHex(primary, '#05080a', 0.88);
      const darkSurface = mixHex(secondary, '#111820', 0.84);
      const darkText = adjustForContrast(mixHex(secondary, '#f7fbfc', 0.88), [darkBg, darkSurface], 4.5);
      const darkPrimary = adjustForContrast(primary, [darkBg, darkSurface], 3.0);
      const darkLink = adjustForContrast(secondary, [darkBg, darkSurface], 4.5);

      return {
        light: {
          '--primary': primary,
          '--secondary': link,
          '--bg': bg,
          '--surface': surface,
          '--text': text,
          ...supportVars(primary, surface, text),
        },
        dark: {
          '--primary': darkPrimary,
          '--secondary': darkLink,
          '--bg': darkBg,
          '--surface': darkSurface,
          '--text': darkText,
          ...supportVars(darkPrimary, darkSurface, darkText),
        },
      };
    };

    const previewMode = () => {
      const theme = document.documentElement.dataset.theme || 'auto';
      if (theme === 'dark') {
        return 'dark';
      }
      if (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        return 'dark';
      }
      return 'light';
    };

    const updateThemePreview = () => {
      const enabled = !customThemeToggle || customThemeToggle.checked;
      const seedColors = {};
      themeColorInputs.forEach((input) => {
        input.disabled = !enabled;
        input.closest('.field')?.classList.toggle('muted-control', !enabled);
        seedColors[input.dataset.themeSeed] = input.value;
      });

      ['--primary', '--secondary', '--bg', '--surface', '--text', '--primary-strong', '--on-primary', '--surface-strong', '--line', '--muted'].forEach((property) => {
        themePreview.style.removeProperty(property);
      });

      if (enabled && seedColors.primary && seedColors.secondary) {
        const derived = deriveTheme(seedColors.primary, seedColors.secondary)[previewMode()];
        Object.entries(derived).forEach(([property, value]) => {
          themePreview.style.setProperty(property, value);
        });
      }
    };

    themeColorInputs.forEach((input) => {
      input.addEventListener('input', updateThemePreview);
    });
    customThemeToggle?.addEventListener('change', updateThemePreview);
    new MutationObserver(updateThemePreview).observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['data-theme'],
    });
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateThemePreview);
    updateThemePreview();
  }

  const logoInput = document.querySelector('[data-logo-input]');
  const logoPreviewTargets = Array.from(document.querySelectorAll('[data-logo-preview-target]'));
  const logoPlaceholders = Array.from(document.querySelectorAll('[data-logo-placeholder]'));
  if (logoInput && logoPreviewTargets.length > 0) {
    let previewUrl = null;
    logoInput.addEventListener('change', () => {
      const file = logoInput.files && logoInput.files[0] ? logoInput.files[0] : null;
      if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
        previewUrl = null;
      }

      if (!file) {
        return;
      }

      previewUrl = URL.createObjectURL(file);
      logoPreviewTargets.forEach((image) => {
        image.src = previewUrl;
        image.hidden = false;
      });
      logoPlaceholders.forEach((placeholder) => {
        placeholder.hidden = true;
      });
    });
  }
});


