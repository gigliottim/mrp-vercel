document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('[data-register-wizard]');
  if (!form) {
    return;
  }

  const stepNodes = Array.from(form.querySelectorAll('[data-wizard-step]'));
  const nextButton = form.querySelector('[data-wizard-next]');
  const prevButton = form.querySelector('[data-wizard-prev]');
  const submitButton = form.querySelector('[data-wizard-submit]');
  const progressBar = document.querySelector('[data-wizard-progress]');
  const companyNameInput = form.querySelector('#company_name');
  const dbPreviewNode = form.querySelector('[data-db-preview]');
  const generalErrorAlert = document.querySelector('.alert.alert-danger');

  let currentStep = 1;
  const totalSteps = stepNodes.length;

  const normalizeCompanyName = (value) => {
    const cleaned = value
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/_+/g, '_')
      .replace(/^_+|_+$/g, '');

    const token = cleaned === '' ? 'empresa' : cleaned;
    return `mrp_${token}`.slice(0, 63);
  };

  const updatePreview = () => {
    if (!dbPreviewNode || !companyNameInput) {
      return;
    }

    dbPreviewNode.textContent = normalizeCompanyName(companyNameInput.value || '');
  };

  const setStep = (step) => {
    currentStep = Math.min(Math.max(step, 1), totalSteps);

    stepNodes.forEach((node, index) => {
      const visible = index + 1 === currentStep;
      node.classList.toggle('d-none', !visible);
    });

    if (prevButton) {
      prevButton.classList.toggle('d-none', currentStep === 1);
    }

    if (nextButton) {
      nextButton.classList.toggle('d-none', currentStep === totalSteps);
    }

    if (submitButton) {
      submitButton.classList.toggle('d-none', currentStep !== totalSteps);
    }

    if (progressBar) {
      const percentage = Math.round((currentStep / totalSteps) * 100);
      progressBar.style.width = `${percentage}%`;
      progressBar.textContent = `Paso ${currentStep} de ${totalSteps}`;
    }

    updatePreview();
  };

  const validateCurrentStep = () => {
    const node = stepNodes[currentStep - 1];
    if (!node) {
      return true;
    }

    const controls = node.querySelectorAll('input, select, textarea');
    for (const control of controls) {
      if (!control.checkValidity()) {
        control.reportValidity();
        return false;
      }
    }

    return true;
  };

  if (nextButton) {
    nextButton.addEventListener('click', () => {
      if (!validateCurrentStep()) {
        return;
      }

      setStep(currentStep + 1);
    });
  }

  if (prevButton) {
    prevButton.addEventListener('click', () => {
      setStep(currentStep - 1);
    });
  }

  if (companyNameInput) {
    companyNameInput.addEventListener('input', updatePreview);
  }

  // Ensure all step fields are enabled before submit so the backend receives full payload.
  form.addEventListener('submit', () => {
    const controls = form.querySelectorAll('input, select, textarea, button');
    controls.forEach((control) => {
      control.disabled = false;
    });
  });

  const firstInvalidField = form.querySelector('.is-invalid');
  if (firstInvalidField) {
    const parentStep = firstInvalidField.closest('[data-wizard-step]');
    const step = parentStep ? Number(parentStep.getAttribute('data-wizard-step')) : 1;
    setStep(step || 1);
  } else if (generalErrorAlert) {
    // Runtime errors happen after submitting on step 3; return user to confirmation step.
    setStep(totalSteps);
  } else {
    setStep(1);
  }
});
