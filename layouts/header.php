<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PJR Parking System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-bg: #f4f7fe;
            --sidebar-bg: #111c43;
            --sidebar-text: #a3aed1;
            --sidebar-hover: #ffffff;
            --sidebar-hover-bg: rgba(255, 255, 255, 0.05);
            --card-shadow: 0 4px 20px 0 rgba(0,0,0,0.05);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary-bg);
            color: #333;
            overflow-x: hidden;
        }

        /* Sidebar Styling */
        #sidebar {
            min-width: 260px;
            max-width: 260px;
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            min-height: 100vh;
            transition: all 0.3s;
            position: fixed;
            height: 100%;
            z-index: 1000;
            overflow-y: auto;
        }

        #sidebar .sidebar-header {
            padding: 24px;
            background: rgba(0,0,0,0.1);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        #sidebar .sidebar-header h3 {
            color: #fff;
            font-weight: 700;
            font-size: 1.25rem;
            margin: 0;
            letter-spacing: 0.5px;
        }

        #sidebar ul p {
            padding: 10px 24px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            color: rgba(255,255,255,0.4);
            margin: 15px 0 0 0;
        }

        #sidebar ul li a {
            padding: 12px 24px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            color: var(--sidebar-text);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        #sidebar ul li a i {
            margin-right: 12px;
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }

        #sidebar ul li.active > a,
        #sidebar ul li a:hover {
            color: var(--sidebar-hover);
            background: var(--sidebar-hover-bg);
            border-left: 3px solid #4361ee;
        }

        #sidebar ul li a[aria-expanded="true"] {
            color: #fff;
            background: rgba(0,0,0,0.2);
        }

        #sidebar .collapse ul li a {
            padding-left: 60px;
            font-size: 0.9rem;
        }

        /* Content Styling */
        #content {
            width: calc(100% - 260px);
            min-height: 100vh;
            transition: all 0.3s;
            position: absolute;
            top: 0;
            right: 0;
        }

        /* Navbar Styling */
        .navbar {
            padding: 15px 30px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        .navbar-btn {
            background: none;
            border: none;
            font-size: 1.25rem;
            color: #64748b;
            cursor: pointer;
            padding: 5px 10px;
        }

        /* Card Customization */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 24px;
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #f1f5f9;
            padding: 16px 24px;
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
        }

        .content-wrapper {
            padding: 30px;
        }

        .page-title {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 24px;
            font-size: 1.5rem;
        }

        /* Utility Classes */
        .badge {
            padding: 0.5em 0.75em;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .table > :not(caption) > * > * {
            padding: 12px 16px;
            vertical-align: middle;
        }

        .table > thead {
            background-color: #f8fafc;
        }

        .table th {
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="wrapper d-flex align-items-stretch">
