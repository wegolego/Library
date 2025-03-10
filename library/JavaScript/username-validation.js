document.addEventListener('DOMContentLoaded', function () {
    const usernameInput = document.getElementById('username');
    const feedback = document.getElementById('username-feedback');

    usernameInput.addEventListener('input', function () {
        const username = usernameInput.value;

        if (username.length < 8) {
            feedback.textContent = 'Username must be at least 8 characters long';
            feedback.style.color = 'red';
        } else {
            fetch(`check-username.php?username=${encodeURIComponent(username)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.available) {
                        feedback.textContent = 'Username is available';
                        feedback.style.color = 'green';
                    } else {
                        feedback.textContent = data.message;
                        feedback.style.color = 'red';
                    }
                })
                .catch(error => {
                    feedback.textContent = 'Error checking username';
                    feedback.style.color = 'red';
                });
        }
    });
});
