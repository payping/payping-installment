// main-page start
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.nav-tab');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Remove active classes
            tabs.forEach(t => t.classList.remove('nav-tab-active'));
            contents.forEach(c => c.classList.remove('active'));
            
            // Add active classes
            tab.classList.add('nav-tab-active');
            const target = document.getElementById(tab.dataset.target);
            if(target) target.classList.add('active');
        });
    });
});
// main-page end

// setting-page start
document.addEventListener('DOMContentLoaded', function() {
    const oauthBtn = document.getElementById('oauth-initiate');
    const tokenField = document.getElementById('payping_api_token');
    const oauthModal = document.getElementById('oauth-modal');

    // Initiate OAuth flow
    oauthBtn.addEventListener('click', function() {
        const authUrl = this.dataset.authUrl;
        oauthModal.style.display = 'block';
        document.getElementById('oauth-frame').src = authUrl;
    });

    // Handle OAuth callback
    window.addEventListener('message', function(e) {
        if(e.origin !== 'https://payping.ir') return;
        
        if(e.data.token) {
            tokenField.value = e.data.token;
            oauthModal.style.display = 'none';
            
            // Automatically submit form to save token
            document.getElementById('payping-settings').submit();
        }
    });
});

jQuery(document).ready(function($) {
    $('#payping_use_digits').change(function() {
        $('.non-digits-option').toggle(!this.checked);
    });
});

// setting-page end