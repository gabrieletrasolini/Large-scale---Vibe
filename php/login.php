<?php
session_start();
if (isset($_SESSION['success_message'])) {
    echo "<div class='success-message'>" . $_SESSION['success_message'] . "</div>";
    unset($_SESSION['success_message']); // Rimuovi il messaggio dalla sessione dopo che è stato mostrato
}
// Variabile per l'errore (se ci sono errori, la mostreremo)
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica se i campi esistono
    $username = isset($_POST['Username']) ? trim($_POST['Username']) : '';
    $password = isset($_POST['Password']) ? trim($_POST['Password']) : '';

    // Verifica che i campi non siano vuoti
    if (empty($username) && empty($password)) {
        $error = "Username e Password non possono essere vuoti!";
    } elseif (empty($username)) {
        $error = "Username non inserito!";
    } elseif (empty($password)) {
        $error = "Password non inserita!";
    }else {
        // Procedi con la connessione a MongoDB e il login
        try {
            $manager = new MongoDB\Driver\Manager("mongodb://mongo:27017");

            $query = new MongoDB\Driver\Query(['username' => $username]);
            $cursor = $manager->executeQuery('admin.User', $query);
            
            $userFound = false;
            foreach ($cursor as $document) {
                $userFound = true;
                // Confronta la password
                if (password_verify($password, $document->password)) {
                    $_SESSION['utente_loggato'] = $username;  // Set sessione
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "Password errata!";
                }
            }

            if (!$userFound) {
                $error = "Username not found!";
            }
        } catch (MongoDB\Driver\Exception\Exception $e) {
            echo "Errore nella connessione a MongoDB: " . $e->getMessage();
        }
    }
}
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <style> 
    .success-message {
    color: green;
    font-size: 18px;
    margin-bottom: 15px;
    text-align: center;
    font-weight: bold;
    }
    </style>
    <!-- basic -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- mobile metas -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport" content="initial-scale=1, maximum-scale=1">
    <!-- site metas -->
    <title>PROJECT</title>
    <meta name="keywords" content="">
    <meta name="description" content="">
    <meta name="author" content="">
    <!-- bootstrap css -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <!-- style css -->
    <link rel="stylesheet" href="css/style.css">
    <!-- Responsive-->
    <link rel="stylesheet" href="css/responsive.css">
    <!-- fevicon -->
    <link rel="icon" href="images/fevicon.png" type="image/gif" />
    <!-- Scrollbar Custom CSS -->
    <link rel="stylesheet" href="css/jquery.mCustomScrollbar.min.css">
    <!-- Tweaks for older IEs-->
    <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.css" media="screen">
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script><![endif]-->
</head>
<!-- body -->

<body class="main-layout" >
    
    <!-- loader  -->
    <div class="loader_bg">
        <div class="loader"><img src="images/loading.gif" alt="#" /></div>
    </div>
 
    <div class="footer" style="background: url('images/home_background.jpg') no-repeat center center fixed; background-size: cover; min-height: 100vh;">
            <div class="container">
                <div class="row">
                    <div class="col-lg-3 col-md-6 col-sm-12 width">
                        <div class="address">
                            
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-12 width">
                        <div class="address">
                            <h1 style="font-family: Arial, sans-serif;font-size: 40px;font-weight: bold;text-align:center;text-transform: uppercase;letter-spacing: 2px;text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);background: linear-gradient(135deg, #ff7f50, #ff4500);-webkit-background-clip: text;color: transparent;margin: 0;">LOGIN </h1>
                            <h3>Please enter your login credentials to access your account.</h3>
                            
                            <form method="POST" action="login.php">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <input class="contactus" placeholder="Username" type="text" name="Username">
                                    </div>
                                    <div class="col-sm-12">
                                        <input class="contactus" placeholder="Password" type="password" name="Password">
                                    </div>
                                    <div class="col-sm-12" style="margin-left: 200px">
                                        <button class="send">Login</button>
                                    </div>
                                </div>
                            </form>
                            <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
                            <div style="text-align: center; margin-top: 10px;">
                                <a href="register.php" style="color: #ff5e00; font-weight: bold; text-decoration: none;">Don't have an account? Register here.</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 width">
                        <div class="address">
                            

                        </div>
                    </div>
                </div>
            </div>
           
        </div>
    <!--  footer -->
    <footr id="footer_with_contact">
        
    </footr>
    <!-- end footer -->
    <!-- Javascript files-->
    <script src="js/jquery.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/jquery-3.0.0.min.js"></script>
    <script src="js/plugin.js"></script>
    <!-- sidebar -->
    <script src="js/jquery.mCustomScrollbar.concat.min.js"></script>
    <script src="js/custom.js"></script>
    <script src="https:cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.js"></script>
    <script>
        $(document).ready(function() {
            $(".fancybox").fancybox({
                openEffect: "none",
                closeEffect: "none"
            });

            $(".zoom").hover(function() {

                $(this).addClass('transition');
            }, function() {

                $(this).removeClass('transition');
            });
        });
    </script>
</body>

</html>