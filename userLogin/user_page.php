<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cricket Ticket Booking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hero-gradient {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        .match-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        .seat {
            transition: all 0.3s ease;
        }
        .seat:hover:not(.booked) {
            transform: scale(1.1);
        }
        .seat.booked {
            background-color: #e53e3e;
            cursor: not-allowed;
        }
        .seat.selected {
            background-color: #38a169;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Login Form -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4 max-w-md">
            <div class="card shadow-lg rounded-lg overflow-hidden">
                <div class="card-header bg-blue-800 text-white p-6 text-center">
                    <h2 class="text-2xl font-bold">User Login</h2>
                </div>
                <div class="card-body p-6">
                    <form method="POST" action="auth.php">
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="email">Email</label>
                            <input type="email" name="email" required class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div class="mb-6">
                            <label class="block text-gray-700 mb-2" for="password">Password</label>
                            <input type="password" name="password" required class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <button type="submit" name="login" class="w-full bg-blue-700 hover:bg-blue-800 text-white py-2 px-4 rounded-lg">Login</button>
                        <p class="mt-4 text-center">
                            Don't have an account? <a href="register.php" class="text-blue-600 hover:underline">Register here</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Registration Form -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4 max-w-md">
            <div class="card shadow-lg rounded-lg overflow-hidden">
                <div class="card-header bg-blue-800 text-white p-6 text-center">
                    <h2 class="text-2xl font-bold">Create Account</h2>
                </div>
                <div class="card-body p-6">
                    <form method="POST" action="auth.php">
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="name">Full Name</label>
                            <input type="text" name="name" required class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="email">Email</label>
                            <input type="email" name="email" required class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="phone">Phone Number</label>
                            <input type="tel" name="phone" required class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="password">Password</label>
                            <input type="password" name="password" required class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div class="mb-6">
                            <label class="block text-gray-700 mb-2" for="confirm_password">Confirm Password</label>
                            <input type="password" name="confirm_password" required class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <button type="submit" name="register" class="w-full bg-blue-700 hover:bg-blue-800 text-white py-2 px-4 rounded-lg">Register</button>
                        <p class="mt-4 text-center">
                            Already have an account? <a href="login.php" class="text-blue-600 hover:underline">Login here</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>

    
    

    
</body>
</html>
