// validation.js
document.addEventListener('DOMContentLoaded', function() {
    const clubForm = document.getElementById('clubForm');

    if (clubForm) {
        clubForm.addEventListener('submit', function(e) {
            let isValid = true;
            let errorMessage = [];

            // Basic required check
            const inputs = clubForm.querySelectorAll('input[required], select[required]');
            inputs.forEach(function(input) {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = 'red';
                    errorMessage.push(`${input.previousElementSibling.innerText} is required.`);
                } else {
                    input.style.borderColor = '#ccc';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert(errorMessage.join('\n'));
            }
        });
    }
});
