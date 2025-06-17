// Set browser info - tunggu sampai DOM ready
document.addEventListener('DOMContentLoaded', function() {
    const browserInfoElement = document.getElementById('browser-info');
    if (browserInfoElement) {
        browserInfoElement.textContent = navigator.userAgent.split(' ').pop();
    }
});