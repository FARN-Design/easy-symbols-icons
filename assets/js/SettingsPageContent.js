document.addEventListener('DOMContentLoaded', function() {
    const removeButtons = document.querySelectorAll('.remove-font');
    
    removeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const fontToRemove = button.getAttribute('data-font');
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;

            const fontInput = document.createElement('input');
            fontInput.type = 'hidden';
            fontInput.name = 'font_to_remove';
            fontInput.value = fontToRemove;
            form.appendChild(fontInput);

            const nonceInput = document.createElement('input');
            nonceInput.type = 'hidden';
            nonceInput.name = 'remove_font_nonce';
            nonceInput.value = EASYICON.remove_nonce;
            form.appendChild(nonceInput);

            document.body.appendChild(form);
            form.submit();
        });
    });

    const popup = document.getElementById('default-fonts-popup');
    if (popup) {
        popup.style.display = 'flex';

        document.getElementById('close-popup').addEventListener('click', () => {
            popup.style.display = 'none';
        });

        document.getElementById('download-default-fonts').addEventListener('click', () => {
            fetch(EASYICON.rest_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': EASYICON.rest_nonce,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) throw new Error("Network response was not ok");
                return response.json();
            })
            .then(() => {
                alert(EASYICON.success_message);
                window.location.reload();
            })
            .catch(error => {
                alert(EASYICON.error_message);
                console.error(error);
            });
        });
    }
});
