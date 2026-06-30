<!DOCTYPE html>
<html>
<head>
    <title>Daftar - RPS Atelier</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Register for RPS Atelier - Rock Paper Scissors Ranked Game">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style_auth.css">
</head>

<body class="auth-body">

<div class="auth-container">

    <h2 class="title">RPS ATELIER</h2>
    <p class="subtitle">Buat Akun Baru</p>

    <div class="auth-card">

        <form method="POST" action="register_process.php">

            <input type="text" name="username" class="form-control input-custom"
                placeholder="Username" required>

            <input type="password" name="password" class="form-control input-custom"
                placeholder="Password" required>

            <button class="btn btn-login w-100 mt-2">Daftar</button>

            <div class="text-center mt-3">
                <a href="login.php" class="link-small">Sudah punya akun? Login</a>
            </div>

        </form>

    </div>

</div>

<script>
window.RpsAudioConfig = {
    track: 'audio/login-register.mp3',
    trackKey: 'login-register'
};
</script>
<script src="js/audio-manager.js"></script>
</body>
</html>
