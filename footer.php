<!-- footer.php -->
<footer class="footer">
    <div class="footer-links">
        <a style="color: #6b7280;" href="http://instagram.com/firzi.rabbani">SI Meeting</a>
    </div>
    <div class="footer-copy">
        2026, made with <span class="heart">❤️</span> by Indoarsip
    </div>
</footer>

<!-- Tippy.js for tooltips -->
<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>
<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/animations/scale.css" />
<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/themes/light-border.css" />
<script>
    document.addEventListener('DOMContentLoaded', function() {
        tippy('[title]', {
            content(reference) {
                const title = reference.getAttribute('title');
                reference.removeAttribute('title');
                return title;
            },
            animation: 'scale',
            theme: 'light-border',
            arrow: true,
            allowHTML: true,
            delay: [100, 0]
        });
    });
</script>
