<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Room Ujian</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #ebfef4;
            color: #ffffff;
            min-height: 100vh;
        }

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            background-color: #a4cdb8;
            border-bottom: 1px solid #46a273;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #5e8b74;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .user-info .name {
            font-weight: 600;
            font-size: 14px;
        }

        .user-info .role {
            font-size: 11px;
            color: #349e67;
        }

        .notification-icon {
            color: #7b8cae;
            cursor: pointer;
            font-size: 16px;
        }

        /* Main Container */
        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Outer Box Wrapper dengan Border */
        .main-card {
            background-color: #c6f7de;
            border: 1px solid #ceecdd;
            border-radius: 12px;
            padding: 25px 30px;
            box-shadow: 0 8px 24px rgba(140, 249, 130, 0.3);
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 18px;
            font-weight: 600;
        }

        .btn-add {
            background-color: #a4cdb8;
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-add:hover {
            background-color: #92b2a2;
        }

        /* Inner List Card Room */
        .room-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .room-card {
            background-color: #a4cdb8;
            border-radius: 8px;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #d0efe0;
        }

        .room-details {
            display: grid;
            grid-template-columns: 35px 1fr;
            row-gap: 8px;
            font-size: 12px;
        }

        .label {
            color: #60739b;
            font-weight: 600;
        }

        .value {
            color: #ffffff;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .room-stats {
            display: flex;
            gap: 30px;
        }

        .stat-box {
            text-align: right;
        }

        .stat-label {
            font-size: 9px;
            color: #60739b;
            text-transform: uppercase;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 18px;
            font-weight: bold;
        }

        .stat-value.total {
            color: #ffffff;
        }

        .stat-value.submit {
            color: #00bfff;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <div class="navbar">
        <div class="user-profile">
            <div class="avatar">NG</div>
            <div class="user-info">
                <div class="name">Nama Guru</div>
                <div class="role">NIP.007624 | TERVERIFIKASI</div>
            </div>
        </div>
        <div class="notification-icon">
            <i class="fa-regular fa-bell"></i>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">
        <!-- Wrapper Card Utama dengan Border -->
        <div class="main-card">
            
            <div class="header-section">
                <h1 class="page-title">Daftar Room Ujian</h1>
                <button class="btn-add">+ Buat Room</button>
            </div>

            <!-- List Room di dalam Border -->
            <div class="room-list">
                <?php
                // Data dummy room
                $rooms = [
                    [
                        'id' => 'RM-8492',
                        'pw' => 'EXAM2026',
                        'total' => 20,
                        'submit' => 16
                    ],
                    [
                        'id' => 'RM-3105',
                        'pw' => 'KODE899',
                        'total' => 20,
                        'submit' => 16
                    ]
                ];

                foreach ($rooms as $room): 
                ?>
                    <div class="room-card">
                        <div class="room-details">
                            <span class="label">ID</span>
                            <span class="value">: <?= $room['id']; ?></span>
                            <span class="label">PW</span>
                            <span class="value">: <?= $room['pw']; ?></span>
                        </div>
                        <div class="room-stats">
                            <div class="stat-box">
                                <div class="stat-label">TIME (TOTAL SISWA)</div>
                                <div class="stat-value total"><?= $room['total']; ?></div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">SUBMIT</div>
                                <div class="stat-value submit"><?= $room['submit']; ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

</body>
</html>