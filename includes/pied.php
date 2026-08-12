</main>
        <footer class="text-center text-muted small py-3">
            ImpôtsLocaux-SN — Projet académique Master CCA, ESP Dakar
        </footer>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script>
    const boutonBascule = document.getElementById('boutonBasculerSidebar');
    const sidebar = document.getElementById('sidebar');

    if (boutonBascule) {
        boutonBascule.addEventListener('click', function () {
            sidebar.classList.toggle('sidebar-ouverte');
        });
    }

    // Referme automatiquement la sidebar mobile dès qu'on clique un lien de navigation,
    // pour ne pas la laisser ouverte par-dessus la page suivante.
    if (sidebar) {
        sidebar.querySelectorAll('.sidebar-nav a').forEach(function (lien) {
            lien.addEventListener('click', function () {
                sidebar.classList.remove('sidebar-ouverte');
            });
        });
    }
</script>
</body>
</html>