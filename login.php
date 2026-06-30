<?php
session_start();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - RPS Atelier</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Login to RPS Atelier - Rock Paper Scissors Ranked Game">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style_auth.css">
</head>

<body class="auth-body">

<div class="auth-container">

    <h2 class="title">RPS ATELIER</h2>
    <p class="subtitle">Rock Paper Scissors</p>

    <div class="auth-card">

        <!-- ALERT ERROR LOGIN -->
        <?php if(isset($_SESSION['login_error'])): ?>

            <div class="login-error">
                <?= $_SESSION['login_error']; ?>
            </div>

        <?php unset($_SESSION['login_error']); endif; ?>

        <!-- FORM LOGIN -->
        <form method="POST" action="login_process.php">

            <input 
                type="text" 
                name="username" 
                class="form-control input-custom"
                placeholder="Username"
                required
            >

            <input 
                type="password" 
                name="password" 
                class="form-control input-custom"
                placeholder="Password"
                required
            >

            <button type="submit" class="btn btn-login w-100 mt-2">
                Masuk
            </button>

            <div class="text-center mt-3">
                <a href="daftar.php" class="link-small">
                    Belum punya akun? Daftar
                </a>
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
