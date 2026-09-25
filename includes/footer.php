  </main>

  <footer class="site-footer">
    <span>
      <b>
        CopyRight &copy; <?= date('Y') ?>
        <a href="/index.php" class="copyright"><?= e($site_name ?? 'Banten-Exploiter') ?></a>.
        All Rights Reserved.
      </b>
    </span>
  </footer>

</div>

<script>
(function () {
  var t = document.querySelector('.toogle');
  if (!t) return;
  t.addEventListener('click', function () {
    document.querySelector('.mainnav ul').classList.toggle('open');
  });
})();
</script>

</body>
</html>