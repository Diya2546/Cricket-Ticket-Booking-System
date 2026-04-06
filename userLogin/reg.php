<?php 
session_start();

$error = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? '',
    
];
$activeForm = $_SESSION['active_form'] ?? 'login';

session_unset();
function showError($error){
    return !empty($error) ? "<p class='error-message'>$error</p>" :'';
}
function isActiveForm($formName, $activeForm){
    return $formName === $activeForm ? 'active': '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="regi.css">
    <link rel="stylesheet" href="assets\css\bootstrap.min.css">
    <script src="../assets/jquery-3.7.1.min.js"></script>
    
    
</head>
<body>
    <!-- <div class="container">
        <div class="title">
            Registration 
        </div>
        <div class="form">
            <div class="input_field">
                <label for="">UserName</label>
                <input type="text" class="input">
            </div>
            <div class="input_field">
                <label for="">Email</label>
                <input type="email" class="input">
            </div>
            <div class="input_field">
                <label for="">Password</label>
                <input type="password" class="input">
            </div>
            <div class="input_field">
                <label for="">Confirm Password</label>
                <input type="password" class="input">
            </div>
            <div class="input_field_terms">
                <label class="check">
                    <input type="checkbox">
                    <span class="checkmark"></span>
                </label>
                <p>Agree to terms and conditions</p>
            </div>
            <div class="input_field">
                <input type="submit" value="Register">
            </div>
        </div>
    </div> -->
    <center>
    <div class="container">
        <div class="form-box <?= isActiveForm('login', $activeForm); ?>" id="login-form">
            <form action="login_register.php" method="post"> <!-- Correct action -->                <h2>Login</h2>
                <? showError($errors['login']); ?>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="password" required >
                <button type="submit" name="login">Login</button>
                <p>Don't have an account? <a href="#" onclick="showForm('register-form')">Register</a></p>
            </form>
        </div>

        <div class="form-box  <?= isActiveForm('register', $activeForm); ?>" id="register-form">
            <form action="" method="post">
                <h2>Register</h2>
                <? showError($errors['register']); ?>
                
                <input type="text" name="name" placeholder="UserName" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="password" required >
                <select name="role" required>
                    <option value="">--Select Role--</option>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit" name="register">Register</button>
                <p>Already have an account? <a href="#" onclick="showForm('login-form')">Login</a></p>
            </form>
        </div>
    </div>
    </center>
    <script>
        function showForm(formId){
    document.querySelectorAll(".form-box").forEach(form => form.classList.remove("active"));
    document.getElementById(formId).classList.add("active");
}
    </script>
</body>
</html>