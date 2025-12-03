</main>
    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(t('footer.copyright')) ?></p>
        </div>
    </footer>
    <script>
    function toggleTheme() {
        const root = document.documentElement;
        const isDark = root.classList.toggle('dark-theme');
        try {
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        } catch (e) {}
    }
    </script>
</body>
</html>