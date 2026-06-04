<?php
// 1. Carica i dati da $_POST
$requiredFields = ['username', 'password', 'first_name', 'last_name', 'email', 'dob', 'gender', 'bio'];
$requiredFieldsError = ['username', 'password', 'first_name', 'last_name', 'email', 'dob', 'gender', 'bio'];
$errorMessages = array_fill_keys($requiredFieldsError, '');
$formData = array_fill_keys($requiredFields, '');
$errorFound = false; // Variabile che indica se ci sono errori

// Controlla se è stata fatta una richiesta POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($_POST as $key => $value) {
        $formData[$key] = $value ?? '';
    }
    // 2. Controllo campi obbligatori
    foreach ($formData as $key => $value) {
        if ($key !== 'bio' && empty($value)) {
            $errorMessages[$key] = ucfirst(str_replace('_', ' ', $key)) . ' is required.';
        }
    }
    // 3. Validazione extra (es. email)
    if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errorMessages['email'] = "Email non valida.";
    }
    // 4. Connessione a MongoDB
    try {
        $manager = new MongoDB\Driver\Manager("mongodb://mongo:27017");
    } catch (MongoDB\Driver\Exception\Exception $e) {
        die("Errore nella connessione a MongoDB: " . $e->getMessage());
    }
    // 5. Controllo username già esistente
    if (!empty($formData['username'])) {
        $query = new MongoDB\Driver\Query(['username' => $formData['username']]);
        $cursor = $manager->executeQuery('admin.User', $query);

        foreach ($cursor as $document) {
            $errorMessages['username'] = "Username già esistente!";
            break;
        }
    }
    // Itera sugli errori
    foreach ($errorMessages as $error) {
        if (empty($error)) {
            // Se l'errore è vuoto, non ci sono problemi, continua
            continue;
        } else {
            // Se c'è un errore non vuoto, setta la variabile a true
            $errorFound = true;
            break; // Non è necessario continuare a controllare gli altri errori
        }
    }
    // 6. Solo se NON ci sono errori, procedi con l'inserimento
    if (!$errorFound) {     
        try {
            $dob = new MongoDB\BSON\UTCDateTime(strtotime($formData['dob']) * 1000);
            $bio = empty($formData['bio']) ? "Bio non fornita" : $formData['bio'];

            $bulk = new MongoDB\Driver\BulkWrite;

            $dataToInsert = [
                '_id' => new MongoDB\BSON\ObjectId(),
                'username' => $formData['username'],
                'password' => password_hash($formData['password'], PASSWORD_DEFAULT),
                'data_registrazione' => new MongoDB\BSON\UTCDateTime(),
                'profilo' => [
                    'nome' => $formData['first_name'],
                    'cognome' => $formData['last_name'],
                    'email' => $formData['email'],
                    'data_nascita' => $dob,
                    'genere' => $formData['gender'],
                    'bio' => $bio,
                    'ruolo' => 'user',
                ],
                'preferiti' => [
                    'brani' => []
                ],
                'playlist_personali' => []
            ];
            $bulk->insert($dataToInsert);
            $result = $manager->executeBulkWrite('admin.User', $bulk);
            // Imposta un messaggio di successo in sessione
            session_start();
            $_SESSION['success_message'] = 'Registrazione avvenuta con successo!';
            header("Location: login.php");
            exit();
        } catch (MongoDB\Driver\Exception\Exception $e) {
            echo "Errore durante l'inserimento: " . $e->getMessage();
        }
    }
}
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
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
    <style> 
        div.nice-select.contactus {
        display: none !important;
    }
    /* Stile per i campi vuoti che diventano rossi */
    /* Stile per i campi con errore */
    /* Stile per i campi con errore */
    .input-error {
        border-color: red;
    }

    /* Stile per i messaggi di errore */
    .error-message {
        color: red;
        font-size: 18px;
        margin-bottom: 5px;
        text-align: left;
    }

    </style>
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
                            <h1 style="font-family: Arial, sans-serif;font-size: 40px;text-align:center;font-weight: bold;text-transform: uppercase;letter-spacing: 2px;text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);background: linear-gradient(135deg, #ff7f50, #ff4500);-webkit-background-clip: text;color: transparent;margin: 0;">REGISTER </h1>
                            <h3>Please enter your personal information.</h3>
                            <form action="register.php" method="POST">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <?php if ($errorMessages['username']) echo "<div class='error-message'>{$errorMessages['username']}</div>"; ?>
                                        <input class="contactus <?php echo $errorMessages['username'] ? 'input-error' : ''; ?>" placeholder="Username" type="text" name="username" value="<?php echo htmlspecialchars($formData['username']); ?>">
                                    </div>
                                    <div class="col-sm-12">
                                        <?php if ($errorMessages['password']) echo "<div class='error-message'>{$errorMessages['password']}</div>"; ?>
                                        <input class="contactus <?php echo $errorMessages['password'] ? 'input-error' : ''; ?>" placeholder="Password" type="password" name="password" value="<?php echo htmlspecialchars($formData['password']); ?>">
                                    </div>
                                    <div class="col-sm-12">
                                        <?php if ($errorMessages['first_name']) echo "<div class='error-message'>{$errorMessages['first_name']}</div>"; ?>
                                        <input class="contactus <?php echo $errorMessages['first_name'] ? 'input-error' : ''; ?>" placeholder="First Name" type="text" name="first_name" value="<?php echo htmlspecialchars($formData['first_name']); ?>">
                                    </div>
                                    <div class="col-sm-12">
                                        <?php if ($errorMessages['last_name']) echo "<div class='error-message'>{$errorMessages['last_name']}</div>"; ?>
                                        <input class="contactus <?php echo $errorMessages['last_name'] ? 'input-error' : ''; ?>" placeholder="Last Name" type="text" name="last_name" value="<?php echo htmlspecialchars($formData['last_name']); ?>">
                                    </div>
                                    <div class="col-sm-12">
                                        <?php if ($errorMessages['email']) echo "<div class='error-message'>{$errorMessages['email']}</div>"; ?>
                                        <input class="contactus <?php echo $errorMessages['email'] ? 'input-error' : ''; ?>" placeholder="Email" type="email" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>">
                                    </div>
                                    <div class="col-sm-12">
                                        <?php if ($errorMessages['dob']) echo "<div class='error-message'>{$errorMessages['dob']}</div>"; ?>
                                        <input class="contactus <?php echo $errorMessages['dob'] ? 'input-error' : ''; ?>" placeholder="Date of Birth" type="date" name="dob" value="<?php echo htmlspecialchars($formData['dob']); ?>">
                                    </div>
                                    <div class="col-sm-12">
                                        <?php if ($errorMessages['gender']) echo "<div class='error-message'>{$errorMessages['gender']}</div>"; ?>
                                        <select class="contactus <?php echo $errorMessages['gender'] ? 'input-error' : ''; ?>" name="gender">
                                            <option value="" disabled <?php echo $formData['gender'] === '' ? 'selected' : ''; ?>>Select Gender</option>
                                            <option value="male" <?php echo $formData['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo $formData['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo $formData['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-12">
                                        <textarea class="contactus" placeholder="Bio" name="bio" rows="4"><?php echo htmlspecialchars($formData['bio']); ?></textarea>
                                    </div>
                                    <div class="col-sm-12" style="margin-left: 180px">
                                        <button class="send">Register</button>
                                    </div>
                                </div>
                            </form>

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