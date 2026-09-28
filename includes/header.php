<?php
$pageTitle = $pageTitle ?? 'Toastmasters Agenda Generator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="assets/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/agenda.css">
    <style>
        .bg-tm-blue {
            background-color: #004165 !important;
        }
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f9;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-tm-blue shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            RM Toastmaster's Agenda Builder
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="https://rajanmaharjan.com.np" target="_blank" rel="noopener noreferrer">rajanmaharjan.com.np</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="http://areac1.base44.app/" target="_blank" rel="noopener noreferrer">Area C1 Website</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-4">
