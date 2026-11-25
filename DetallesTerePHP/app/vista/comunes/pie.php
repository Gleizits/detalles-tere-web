<?php /** Pie de la plantilla común. */ ?>
    </main>
</div><!-- /app-cuerpo -->

<?php
// El menú (cajón) vive en assets/js/menu.js: la CSP no permite
// scripts en línea, por lo que toda la lógica va en archivos externos.
?>
<script src="<?= url('/assets/js/menu.js') ?>"></script>
<script src="<?= url('/assets/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
