</main>
<footer class="text-center text-muted small py-3">
    ImpôtsLocaux-SN — Projet académique Master CCA, ESP Dakar
</footer>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script>
    const boutonOuvrir = document.getElementById('boutonBasculerSidebar');
    const boutonFermer = document.getElementById('boutonFermerSidebar');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    function ouvrirSidebar() {
        sidebar.classList.add('sidebar-ouverte');
        overlay.classList.add('overlay-visible');
    }
    function fermerSidebar() {
        sidebar.classList.remove('sidebar-ouverte');
        overlay.classList.remove('overlay-visible');
    }

    if (boutonOuvrir) boutonOuvrir.addEventListener('click', ouvrirSidebar);
    if (boutonFermer) boutonFermer.addEventListener('click', fermerSidebar);
    if (overlay) overlay.addEventListener('click', fermerSidebar); // clic en dehors du menu

    // Referme automatiquement la sidebar dès qu'on clique un lien de navigation
    if (sidebar) {
        sidebar.querySelectorAll('.sidebar-nav a').forEach(function (lien) {
            lien.addEventListener('click', fermerSidebar);
        });
    }
</script>
</body>

</html>