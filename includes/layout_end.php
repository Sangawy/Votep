        </div><!-- /.lib-page -->
    </div><!-- /.lib-main -->
</div><!-- /.lib-wrapper -->

<script>
function libToast(text, type = 'success') {
    const area = document.getElementById('libToastArea');
    if (!area) return;
    const toast = document.createElement('div');
    toast.className = 'lib-toast ' + type;
    toast.textContent = text;
    area.appendChild(toast);
    setTimeout(() => { toast.remove(); }, 4200);
}
</script>
</body>
</html>
