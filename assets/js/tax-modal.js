document.addEventListener('DOMContentLoaded', function () {
	var modal = document.getElementById('yoohw-tax-modal');

	if (!modal) return;

	function openModal() {
		modal.classList.add('is-active');
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('yoohw-tax-modal-open');

		var firstInput = modal.querySelector('input, textarea');
		if (firstInput) firstInput.focus();
	}

	function closeModal() {
		modal.classList.remove('is-active');
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('yoohw-tax-modal-open');
	}

	document.addEventListener('click', function (e) {

		// Open modal
		var trigger = e.target.closest('a[href*="#yoohw-tax-invoice-request"], .yoohw-tax-open-modal');
		if (trigger) {
			e.preventDefault();
			openModal();
			return;
		}

		// Close modal
		if (e.target.closest('[data-yoohw-tax-close]')) {
			e.preventDefault();
			closeModal();
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			closeModal();
		}
	});

	// Auto open via URL hash
	if (window.location.hash === '#yoohw-tax-invoice-request') {
		openModal();
	}
});