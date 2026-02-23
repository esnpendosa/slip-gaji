            <!-- Sidebar -->
            <div class="col-md-2 p-0 sidebar d-none d-md-block" id="sidebar">
                <div class="d-flex flex-column h-100">
                    <div class="sidebar-header">
                        <a href="index.php" class="sidebar-brand">
                            <i class="fas fa-cube text-primary"></i>
                            <span>Putra Payroll</span>
                        </a>
                    </div>
                    
                    <div class="sidebar-nav flex-grow-1">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                                    <i class="fas fa-home"></i> <span>Dashboard</span>
                                </a>
                            </li>
                            
                            <li class="nav-header text-muted ps-3 mt-3 mb-2 small text-uppercase fw-bold" style="font-size: 0.7rem;">Penggajian</li>
                            
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'data_slip.php' ? 'active' : ''; ?>" href="data_slip.php">
                                    <i class="fas fa-file-invoice-dollar"></i> <span>Data Slip Gaji</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'generate_slip.php' ? 'active' : ''; ?>" href="generate_slip.php">
                                    <i class="fas fa-plus-circle"></i> <span>Generate Slip</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'send_notif.php' ? 'active' : ''; ?>" href="send_notif.php">
                                    <i class="fab fa-whatsapp"></i> <span>Kirim Notifikasi</span>
                                </a>
                            </li>
                            
                            <li class="nav-header text-muted ps-3 mt-3 mb-2 small text-uppercase fw-bold" style="font-size: 0.7rem;">Master Data</li>
                            
                            <li class="nav-item">
                                <a class="nav-link" href="#karyawanModal" data-bs-toggle="modal">
                                    <i class="fas fa-users"></i> <span>Karyawan</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#periodeModal" data-bs-toggle="modal">
                                    <i class="fas fa-calendar-alt"></i> <span>Periode Gaji</span>
                                </a>
                            </li>
                            
                            <li class="nav-header text-muted ps-3 mt-3 mb-2 small text-uppercase fw-bold" style="font-size: 0.7rem;">Sistem</li>
                            
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'pengaturan.php' ? 'active' : ''; ?>" href="pengaturan.php">
                                    <i class="fas fa-cogs"></i> <span>Pengaturan</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="p-3">
                        <a href="logout.php" class="nav-link text-danger justify-content-center border border-danger rounded-3">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Mobile Toggle Button (Visible only on mobile) -->
            <div class="d-md-none position-fixed top-0 start-0 p-3" style="z-index: 1050;">
                <button class="btn btn-primary shadow-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <!-- Mobile Sidebar Offcanvas -->
            <div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="mobileSidebar" style="background-color: var(--sidebar-bg) !important;">
                <div class="offcanvas-header border-bottom border-secondary">
                    <h5 class="offcanvas-title text-white">
                        <i class="fas fa-cube text-primary me-2"></i> Putra Payroll
                    </h5>
                    <button type="button" class="btn-close btn-close-white text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body p-0">
                    <!-- Duplicate styling from main sidebar for consistency -->
                    <div class="sidebar-nav">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                                    <i class="fas fa-home"></i> <span>Dashboard</span>
                                </a>
                            </li>
                            <!-- ... (Repeat other menu items or better yet, include a common menu file) -->
                            <!-- For simplicity in this step, I'll repeat the links but normally I'd extract this list -->
                             <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'data_slip.php' ? 'active' : ''; ?>" href="data_slip.php">
                                    <i class="fas fa-file-invoice-dollar"></i> <span>Data Slip Gaji</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'generate_slip.php' ? 'active' : ''; ?>" href="generate_slip.php">
                                    <i class="fas fa-plus-circle"></i> <span>Generate Slip</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'send_notif.php' ? 'active' : ''; ?>" href="send_notif.php">
                                    <i class="fab fa-whatsapp"></i> <span>Kirim Notifikasi</span>
                                </a>
                            </li>
                             <li class="nav-item">
                                <a class="nav-link" href="#karyawanModal" data-bs-toggle="modal">
                                    <i class="fas fa-users"></i> <span>Karyawan</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#periodeModal" data-bs-toggle="modal">
                                    <i class="fas fa-calendar-alt"></i> <span>Periode Gaji</span>
                                </a>
                            </li>
                             <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'pengaturan.php' ? 'active' : ''; ?>" href="pengaturan.php">
                                    <i class="fas fa-cogs"></i> <span>Pengaturan</span>
                                </a>
                            </li>
                            <li class="nav-item mt-4">
                                <a href="logout.php" class="nav-link text-danger">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
