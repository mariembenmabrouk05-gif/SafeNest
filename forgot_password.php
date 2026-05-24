<?php
require 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Veuillez saisir votre adresse e-mail.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $temp_password = bin2hex(random_bytes(4));
            $hash = password_hash($temp_password, PASSWORD_BCRYPT);
            
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user['id']]);
            
            require 'PHPMailer-PHPMailer-3cd2a2a/src/Exception.php';
            require 'PHPMailer-PHPMailer-3cd2a2a/src/PHPMailer.php';
            require 'PHPMailer-PHPMailer-3cd2a2a/src/SMTP.php';
            
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'veilleenfantine@gmail.com';
                $mail->Password   = 'rbfa rmaa nvkt jldq';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('veilleenfantine@gmail.com', 'Sécurité SafeNest');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = "Réinitialisation de votre mot de passe Protect";
                $mail->Body    = "
                    <div style='font-family:Arial,sans-serif; background-color:#f4f5f9; padding:20px; border-radius:10px; max-width:600px;'>
                        <h2 style='color:#0B1B44; margin-top:0;'>Réinitialisation du compte</h2>
                        <p style='color:#4b5563; font-size:16px;'>Bonjour <strong>{$user['username']}</strong>,</p>
                        <p style='color:#4b5563; font-size:16px;'>Vous avez demandé à réinitialiser votre mot de passe sur SafeNest.</p>
                        <ul style='background:white; padding:15px; border-radius:5px; font-size:15px; color:#1f2937; list-style:none;'>
                            <li style='margin-bottom:5px;'>Votre nouveau mot de passe temporaire est : <span style='color:#3CB4A8; font-weight:bold; font-size:18px;'>{$temp_password}</span></li>
                        </ul>
                        <p style='color:#4b5563; font-size:15px;'>Veuillez vous connecter avec ce mot de passe et le modifier immédiatement dans l'onglet Profil.</p>
                    </div>";

                $mail->send();
                $success = "Un mot de passe temporaire a été envoyé à cette adresse.";
            } catch (Exception $e) {
                $error = "Erreur lors de l'envoi de l'email : {$mail->ErrorInfo}";
            }
        } else {
            // For security, fake success
            $success = "Si cet email existe, un mot de passe a été envoyé.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Récupération — SafeNest</title>
    <link rel="icon" href="image/logo2.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-[#0B1B44] to-[#6A64B6] flex items-center justify-center min-h-screen p-4 py-10 font-sans" style="font-family: 'Poppins', sans-serif;">
    <div class="bg-[#2C345D] border border-white/10 rounded-[1.5rem] shadow-2xl flex flex-col md:flex-row w-full max-w-[800px] overflow-hidden min-h-[450px]">
        <div class="md:w-5/12 bg-[#0B1B44] p-10 flex flex-col items-center justify-center text-white text-center relative overflow-hidden hidden md:flex">
            <div class="absolute -top-20 -left-20 w-64 h-64 bg-[#EBCC84]/10 rounded-full blur-3xl"></div>
            <img src="image/logo2.png" alt="Logo" class="h-[100px] mb-6 drop-shadow-xl z-10 relative">
            <h1 class="text-2xl font-extrabold mb-3 tracking-tight z-10 relative">SafeNest</h1>
            <p class="text-[#B7AED6] text-xs leading-relaxed z-10 relative">Plateforme de récupération sécurisée.</p>
        </div>

        <div class="md:w-7/12 p-8 lg:p-12 flex flex-col justify-center bg-[#2C345D] relative text-white">
            <div class="mb-6 text-center md:text-left">
                <h2 class="text-2xl font-bold text-white">Mot de passe oublié</h2>
                <p class="text-[#B7AED6] text-sm mt-1">Saisissez l'e-mail lié à votre compte Parent</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-500/20 text-red-200 px-4 py-3 rounded-lg text-sm mb-6 border border-red-500/30 font-medium">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-500/20 text-green-200 px-4 py-3 rounded-lg text-sm mb-6 border border-green-500/30 font-medium">
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold tracking-widest uppercase text-[#B7AED6] mb-1">E-mail de récupération</label>
                    <input type="email" name="email" required 
                           class="w-full py-2 bg-transparent border-0 border-b-[2px] border-[#6A64B6]/50 text-white font-medium outline-none focus:ring-0 focus:border-[#EBCC84] transition-colors">
                </div>
                
                <div class="pt-4">
                    <button type="submit" class="w-[200px] mx-auto md:mx-0 block bg-[#EBCC84] hover:bg-[#d6b772] text-[#0B1B44] font-bold py-3 rounded-full transition shadow-lg">Envoyer le lien</button>
                </div>
            </form>

            <p class="text-center md:text-left text-sm mt-8 text-[#B7AED6]">
                Retour à la <a href="index.php" class="text-[#3CB4A8] font-bold hover:underline">Connexion</a>
            </p>
        </div>
    </div>
</body>
</html>
