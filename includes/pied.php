</main>
        <footer class="text-center text-muted small py-3">
            ImpôtsLocaux-SN — Projet académique Master CCA, ESP Dakar
        </footer>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script>
    // Sur mobile/tablette, la sidebar est cachée par défaut : ce bouton la fait apparaître/disparaître.
    const boutonBascule = document.getElementById('boutonBasculerSidebar');
    if (boutonBascule) {
        boutonBascule.addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('sidebar-ouverte');
        });
    }
</script>
</body>
</html>