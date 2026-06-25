            </div>
        </main>
    </div>
    <script>
    window.__CSRF_TOKEN__ = '<?= csrfToken() ?>';
    window.__FORCE_PASSWORD_CHANGE__ = <?= $forcePasswordChange ? 'true' : 'false' ?>;
    </script>
    <script src="/panel/assets/app.js"></script>
    <?php if ($forcePasswordChange): ?>
    <script>
    async function changeForcePassword() {
        const pw = document.getElementById('newPassword').value;
        const confirm = document.getElementById('confirmPassword').value;
        if (pw.length < 6) return AHPL.toast('Password minimal 6 karakter', 'error');
        if (pw !== confirm) return AHPL.toast('Password tidak cocok', 'error');
        const res = await AHPL.api('/panel/api/settings.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'change_password', password: pw })
        });
        if (res.success) { AHPL.toast('Password diganti!'); setTimeout(() => location.reload(), 1000); }
    }
    </script>
    <?php endif; ?>
</body>
</html>
