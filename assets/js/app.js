document.addEventListener('DOMContentLoaded', () => {
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
});


