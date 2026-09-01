<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Laravel'))</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">
    
    @stack('styles')
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- TomSelect for searchable dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    
    <style>
        :root {
            /* Primary theme colors - Updated with a modern, more appealing palette */
            --bs-primary: #6366F1;        /* Indigo 500 */
            --bs-secondary: #4F46E5;      /* Indigo 600 */
            --bs-success: #10B981;        /* Emerald 500 */
            --bs-info: #3B82F6;           /* Blue 500 */
            --bs-warning: #F59E0B;        /* Amber 500 */
            --bs-danger: #EF4444;         /* Red 500 */
            --bs-light: #F9FAFB;          /* Gray 50 */
            --bs-dark: #1F2937;           /* Gray 800 */
            
            /* UI specific variables - Updated for better contrast and visual appeal */
            --light-bg: #F9FAFB;
            --text-color: #1F2937;
            --text-muted: #6B7280;
            --card-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.1), 0 2px 4px -1px rgba(99, 102, 241, 0.06);
            --sidebar-width: 300px;
            --header-height: 60px;
            --sidebar-bg: #FFFFFF;
            --sidebar-hover: rgba(99, 102, 241, 0.1);
            --sidebar-active: rgba(99, 102, 241, 0.15);
            
            /* Additional theme colors */
            --accent: #A5B4FC;            /* Indigo 300 */
            --card-bg: #FFFFFF;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            position: fixed;
            height: 100vh;
            top: 0;
            left: 0;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            z-index: 999;
            color: var(--text-color);
            overflow-y: auto;
        }
        
        .sidebar.resizing {
            transition: none;
        }
        
        .sidebar-resizer {
            position: absolute;
            top: 0;
            right: 0;
            width: 4px;
            height: 100%;
            cursor: col-resize;
            background: transparent;
            z-index: 1000;
            transition: background 0.2s ease;
        }
        
        .sidebar-resizer:hover {
            background: rgba(99, 102, 241, 0.3);
        }
        
        .sidebar-resizer.dragging {
            background: rgba(99, 102, 241, 0.5);
        }
        
        .content.resizing {
            transition: none;
        }
        
        .content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: all 0.3s ease;
            min-height: 100vh;
            background-color: var(--light-bg);
        }
        
        .brand {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .brand-logo {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.5rem;
            color: var(--bs-primary);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .brand-text {
            font-weight: 600;
            margin-left: 0.75rem;
            font-size: 1.25rem;
            color: var(--text-color);
        }
        
        .nav-item {
            padding: 0;
            margin: 2px 0;
        }
        
        .nav-section-title {
            color: var(--text-muted);
            padding: 1.25rem 1.5rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            color: var(--text-color);
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border-radius: 8px;
            margin: 0 0.75rem;
            position: relative;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .nav-link:hover {
            background-color: var(--sidebar-hover);
            color: var(--bs-primary);
            transform: translateX(2px);
        }
        
        .nav-link.active {
            background-color: var(--sidebar-active);
            color: var(--bs-primary);
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .nav-icon {
            font-size: 1rem;
            margin-right: 0.75rem;
            width: 1.25rem;
            text-align: center;
            opacity: 0.9;
        }
        
        .nav-chevron {
            margin-left: auto;
            opacity: 0.8;
            font-size: 0.7rem;
            transition: transform 0.3s ease;
        }
        
        [aria-expanded="true"] .nav-chevron {
            transform: rotate(90deg);
            opacity: 1;
        }
        
        .sidebar .collapse {
            background-color: transparent;
            padding-left: 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .sidebar .collapse.show {
            max-height: 1000px;
        }
        
        .sidebar .collapse .nav-link {
            padding-left: 3.5rem;
            font-size: 0.9rem;
            color: var(--text-color);
            background-color: transparent;
        }
        
        .sidebar .collapse .nav-link:hover {
            color: var(--bs-primary);
            background-color: var(--sidebar-hover);
        }
        
        .sidebar .collapse .nav-link.active {
            color: var(--bs-primary);
            background-color: var(--sidebar-active);
        }
        
        @media (max-width: 991.98px) {
            .sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
                box-shadow: none;
            }
            
            .content {
                margin-left: 0;
            }
            
            .sidebar.show {
                margin-left: 0;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            }
            
            .content.shifted {
                margin-left: var(--sidebar-width);
            }
            
            .sidebar-resizer {
                display: none;
            }
        }
        
        .navbar {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            min-height: 60px !important;
            height: auto !important;
            max-height: none !important;
        }
        
        .navbar-toggler {
            border: none;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background-color 0.2s;
        }
        
        .navbar-toggler:hover {
            background-color: rgba(13, 110, 253, 0.1);
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
        }
        
        /* Ensure navbar is not affected by other styling */
        .navbar * {
            box-sizing: border-box;
        }
        
        .navbar .container-fluid {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }
        
        .card {
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            border: none;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            background-color: white;
        }
        
        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }
        
        .card-header {
            border-radius: 1rem 1rem 0 0 !important;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            background-color: white;
            padding: 1.25rem 1.5rem;
        }
        
        .btn {
            border-radius: 0.5rem;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #9F83DF 0%, #9ECFFF 100%);
            border-color: #9F83DF;
            box-shadow: 0 2px 4px rgba(159, 131, 223, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #8b73c9 0%, #87b9ea 100%);
            border-color: #8b73c9;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(159, 131, 223, 0.4);
        }
        
        .btn-light {
            background-color: var(--bs-light);
            border-color: var(--bs-light);
            color: var(--text-color);
        }
        
        .btn-light:hover {
            background-color: #e9ecef;
            border-color: #e9ecef;
        }
        
        .text-primary {
            color: var(--bs-primary) !important;
        }
        
        .text-secondary {
            color: var(--bs-secondary) !important;
        }
        
        .bg-primary-subtle {
            background-color: rgba(159, 131, 223, 0.15) !important;
        }
        
        .bg-success-subtle {
            background-color: rgba(199, 245, 217, 0.15) !important;
        }
        
        .bg-warning-subtle {
            background-color: rgba(255, 229, 180, 0.15) !important;
        }
        
        .fs-xs {
            font-size: 0.75rem !important;
        }
        
        .fs-sm {
            font-size: 0.875rem !important;
        }

        .dropdown-menu {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            padding: 0.75rem 0.5rem;
            min-width: 200px;
        }
        
        .dropdown-item {
            border-radius: 0.5rem;
            padding: 0.6rem 1rem;
            transition: all 0.2s;
        }
        
        .dropdown-item:hover {
            background-color: rgba(159, 131, 223, 0.1);
            transform: translateX(2px);
        }
        
        .dropdown-item i {
            width: 1.25rem;
            text-align: center;
            margin-right: 0.5rem;
        }
        
        .avatar {
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #9F83DF 0%, #9ECFFF 100%);
            color: white;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Button styles */
        .btn-icon {
            width: 38px;
            height: 38px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }
        
        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Animation styles */
        .sidebar .collapse {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .sidebar .collapse.show {
            max-height: 1000px;
        }
        
        /* Table styles */
        .table-responsive {
            max-height: 500px !important; /* Fixed height */
            overflow-y: auto !important;
            overflow-x: auto !important;
            display: block !important;
            width: 100% !important;
            border: 1px solid #dee2e6;
        }
        
        .table-responsive thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table-responsive thead th {
            background-color: #f8f9fa !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 10 !important;
        }
        
        /* Ensure tables don't extend endlessly */
        .wide-table {
            margin-bottom: 0 !important;
        }
        
        /* Disable any styles that might interfere with scrolling */
        .disable-fixed-scrollbar {
            max-height: 500px !important;
            overflow-y: auto !important;
            overflow-x: auto !important;
        }
        
        /* Fix horizontal scrolling */
        .table-responsive {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
            display: block !important;
            width: 100% !important;
        }
        
        /* Make tables have proper width and horizontal scroll */
        .table-responsive table {
            width: auto !important;
            min-width: 100% !important;
        }
        
        /* Page transitions */
        .page-enter {
            opacity: 0;
            transform: translateY(10px);
        }
        
        .page-enter-active {
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.3s, transform 0.3s;
        }
        
        /* Custom styles for specific components */
        .stat-card {
            border-radius: 1rem;
            border-left: 4px solid var(--bs-primary);
            box-shadow: var(--card-shadow);
            background-color: var(--card-bg);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(159, 131, 223, 0.15);
        }
        
        .stat-card.primary {
            border-left-color: var(--bs-primary);
        }
        
        .stat-card.secondary {
            border-left-color: var(--bs-secondary);
        }
        
        .stat-card.info {
            border-left-color: var(--bs-info);
        }
        
        .stat-card.warning {
            border-left-color: var(--bs-warning);
        }
        
        .stat-card.danger {
            border-left-color: var(--bs-danger);
        }
        
        /* Improved table header styling */
        .table-responsive.disable-fixed-scrollbar thead th {
            background-color: white;
            position: sticky;
            top: 0;
            z-index: 11;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        /* Table with fixed headers that work with horizontal scroll */
        .table-responsive table thead th {
            position: sticky;
            top: 0;
            background-color: white;
            z-index: 10;
        }
        
        /* Tom Select Dropdown Fix - Ensure dropdown appears above other elements */
        .ts-dropdown {
            z-index: 9999 !important;
            position: absolute !important;
        }
        
        .ts-control {
            position: relative;
            z-index: 1;
        }
        
        /* Ensure form containers don't clip dropdowns */
        .form-group {
            position: relative;
            overflow: visible !important;
        }
        
        .card-body {
            overflow: visible !important;
        }
        
        .input-group {
            position: relative;
            overflow: visible !important;
        }
        
        /* Modal dropdown fix */
        .modal .ts-dropdown {
            z-index: 10000 !important;
        }
        
        /* Ensure dropdowns in cards are visible */
        .card {
            overflow: visible !important;
        }
        
        /* Fix for nested dropdowns */
        .ts-wrapper {
            position: relative;
            z-index: 1;
        }
        
        .ts-dropdown-content {
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-resizer" id="sidebarResizer"></div>
        @include('components.sidebar')
    </div>
    
    <div class="content" id="content">
        @include('components.navbar')
        
        <div class="py-3">
            @yield('content')
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- <script src="{{ asset('js/fixed-table-scroll.js') }}"></script> -->
    <script>
        // Disable fixed-table-scroll.js
        window.disableFixedTableScroll = true;
        
        // Toggle sidebar
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOM loaded, initializing tables...");
            
            // Disable fixed-table-scroll.js completely
            if (window.disableFixedTableScroll) {
                console.log("Disabling fixed-table-scroll.js");
                window.disableFixedTableScroll = true;
            }
            
            // Fix untuk tabel scrolling horizontal
            const tableResponsives = document.querySelectorAll('.table-responsive');
            console.log("Found " + tableResponsives.length + " responsive tables");
            
            tableResponsives.forEach((container, index) => {
                console.log("Processing table #" + index);
                
                // Remove any dynamic inline styles that might be causing issues
                container.style = "";
                
                // Apply only the essential styles directly
                container.style.maxHeight = "500px";
                container.style.overflowY = "auto";
                container.style.overflowX = "auto";
                container.style.display = "block";
                container.style.width = "100%";
                
                const table = container.querySelector('table');
                if (table) {
                    console.log("Table found in container #" + index);
                    // Reset any dynamic styles
                    table.style = "";
                    
                    // Set minimal styling
                    table.style.width = "100%";
                    if (table.classList.contains('wide-table')) {
                        table.style.minWidth = "1200px";
                        console.log("Applied wide-table style");
                    }
                    
                    // Disable all event listeners for scroll that might be interfering
                    const newContainer = container.cloneNode(true);
                    container.parentNode.replaceChild(newContainer, container);
                }
            });
            
            const sidebarToggleBtn = document.getElementById('sidebarToggle');
            if (sidebarToggleBtn) {
                sidebarToggleBtn.addEventListener('click', function() {
                    document.getElementById('sidebar').classList.toggle('show');
                    document.getElementById('content').classList.toggle('shifted');
                });
            }
            
            // Sidebar Resizer - Drag to resize
            const sidebarResizer = document.getElementById('sidebarResizer');
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            
            if (sidebarResizer && sidebar && content) {
                // Load saved sidebar width from localStorage
                const savedWidth = localStorage.getItem('sidebarWidth');
                if (savedWidth) {
                    const width = parseInt(savedWidth);
                    if (width >= 200 && width <= 500) {
                        document.documentElement.style.setProperty('--sidebar-width', width + 'px');
                    }
                }
                
                let isResizing = false;
                let startX = 0;
                let startWidth = 0;
                
                sidebarResizer.addEventListener('mousedown', function(e) {
                    isResizing = true;
                    startX = e.clientX;
                    startWidth = parseInt(window.getComputedStyle(sidebar).width, 10);
                    
                    sidebar.classList.add('resizing');
                    content.classList.add('resizing');
                    sidebarResizer.classList.add('dragging');
                    
                    document.body.style.cursor = 'col-resize';
                    document.body.style.userSelect = 'none';
                    
                    e.preventDefault();
                });
                
                document.addEventListener('mousemove', function(e) {
                    if (!isResizing) return;
                    
                    const diff = e.clientX - startX;
                    let newWidth = startWidth + diff;
                    
                    // Min width: 200px, Max width: 500px
                    newWidth = Math.max(200, Math.min(500, newWidth));
                    
                    document.documentElement.style.setProperty('--sidebar-width', newWidth + 'px');
                });
                
                document.addEventListener('mouseup', function() {
                    if (isResizing) {
                        isResizing = false;
                        sidebar.classList.remove('resizing');
                        content.classList.remove('resizing');
                        sidebarResizer.classList.remove('dragging');
                        
                        document.body.style.cursor = '';
                        document.body.style.userSelect = '';
                        
                        // Save width to localStorage
                        const currentWidth = parseInt(window.getComputedStyle(sidebar).width, 10);
                        localStorage.setItem('sidebarWidth', currentWidth);
                    }
                });
            }
            
            // Responsive behavior
            function checkWindowSize() {
                const sidebar = document.getElementById('sidebar');
                const content = document.getElementById('content');
                
                if (window.innerWidth <= 991.98) {
                    sidebar.classList.remove('show');
                    content.classList.remove('shifted');
                } else {
                    sidebar.classList.remove('show');
                    content.classList.remove('shifted');
                }
            }
            
            window.addEventListener('resize', checkWindowSize);
            checkWindowSize();
            
            // Dropdown arrow rotation
            const dropdownToggles = document.querySelectorAll('[data-bs-toggle="collapse"]');
            
            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const icon = this.querySelector('.nav-chevron');
                    if (icon) {
                        if (this.getAttribute('aria-expanded') === 'true') {
                            icon.style.transform = 'rotate(90deg)';
                        } else {
                            icon.style.transform = 'rotate(0deg)';
                        }
                    }
                    
                    // Animation for collapsing element
                    const targetId = this.getAttribute('href');
                    const targetElement = document.querySelector(targetId);
                    
                    if (targetElement) {
                        if (this.getAttribute('aria-expanded') === 'true') {
                            targetElement.style.maxHeight = '0';
                            setTimeout(() => {
                                targetElement.style.maxHeight = targetElement.scrollHeight + 'px';
                            }, 10);
                        } else {
                            targetElement.style.maxHeight = targetElement.scrollHeight + 'px';
                            setTimeout(() => {
                                targetElement.style.maxHeight = '0';
                            }, 10);
                        }
                    }
                });
                
                // Initial state
                const collapse = document.querySelector(toggle.getAttribute('href'));
                const icon = toggle.querySelector('.nav-chevron');
                
                if (icon && collapse && collapse.classList.contains('show')) {
                    icon.style.transform = 'rotate(90deg)';
                    collapse.style.maxHeight = collapse.scrollHeight + 'px';
                } else if (collapse) {
                    collapse.style.maxHeight = '0';
                }
            });
            
            // Highlight active menu item
            const currentPath = window.location.pathname;
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                if (link.getAttribute('href') !== '#' && link.getAttribute('href') !== null) {
                    const linkPath = new URL(link.href, window.location.origin).pathname;
                    if (currentPath === linkPath || currentPath.startsWith(linkPath + '/')) {
                        link.classList.add('active');
                        
                        // If it's a submenu item, expand its parent
                        const parentCollapse = link.closest('.collapse');
                        if (parentCollapse) {
                            const parentToggle = document.querySelector(`[href="#${parentCollapse.id}"]`);
                            if (parentToggle) {
                                parentToggle.setAttribute('aria-expanded', 'true');
                                parentCollapse.classList.add('show');
                                parentCollapse.style.maxHeight = parentCollapse.scrollHeight + 'px';
                                
                                const icon = parentToggle.querySelector('.nav-chevron');
                                if (icon) {
                                    icon.style.transform = 'rotate(90deg)';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
    
    <!-- Diagnostic script for debugging -->
    <script>
        // Log any JavaScript errors
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e.message, 'at', e.filename, 'line', e.lineno);
            
            // Create visible error message for white screen debugging
            const errorDiv = document.createElement('div');
            errorDiv.style.position = 'fixed';
            errorDiv.style.top = '0';
            errorDiv.style.left = '0';
            errorDiv.style.right = '0';
            errorDiv.style.padding = '10px';
            errorDiv.style.background = 'red';
            errorDiv.style.color = 'white';
            errorDiv.style.zIndex = '9999';
            errorDiv.innerHTML = 'JavaScript Error: ' + e.message + ' at ' + e.filename + ' line ' + e.lineno;
            document.body.appendChild(errorDiv);
        });
        
        // Log when Chart.js is loaded successfully
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (window.Chart) {
                    console.log('Chart.js loaded successfully');
                } else {
                    console.error('Chart.js not loaded');
                    
                    // Create visible error message
                    const errorDiv = document.createElement('div');
                    errorDiv.style.position = 'fixed';
                    errorDiv.style.top = '0';
                    errorDiv.style.left = '0';
                    errorDiv.style.right = '0';
                    errorDiv.style.padding = '10px';
                    errorDiv.style.background = 'orange';
                    errorDiv.style.color = 'white';
                    errorDiv.style.zIndex = '9999';
                    errorDiv.innerHTML = 'Chart.js not loaded. This may cause white screens in analytics pages.';
                    document.body.appendChild(errorDiv);
                }
            }, 1000);
        });
    </script>

    <!-- Export Job Handler -->
    <script>
    function showToast(html, borderColor) {
        var container = document.getElementById('export-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'export-toast-container';
            container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:10px;';
            document.body.appendChild(container);
        }
        var toast = document.createElement('div');
        toast.style.cssText = 'background:#fff;border-left:4px solid ' + (borderColor || '#6366F1') + ';padding:16px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);min-width:300px;max-width:420px;font-family:Poppins,sans-serif;font-size:14px;display:flex;align-items:center;gap:12px;';
        toast.innerHTML = html;
        container.appendChild(toast);
        return toast;
    }

    function showLoading() {
        return showToast('<div style="width:20px;min-width:20px;height:20px;border:3px solid #e5e7eb;border-top-color:#6366F1;border-radius:50%;animation:spin 0.8s linear infinite;"></div><div><strong>Export sedang diproses...</strong><br><span style="color:#6B7280;font-size:12px;">Mohon tunggu sebentar</span></div>');
    }

    function showSuccess(toast, downloadUrl) {
        toast.style.borderLeftColor = '#10B981';
        toast.innerHTML = '<div style="width:20px;min-width:20px;height:20px;background:#10B981;border-radius:50%;display:flex;align-items:center;justify-content:center;"><svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><div><strong style="color:#10B981;">Export selesai!</strong><br><a href="' + downloadUrl + '" style="color:#6366F1;font-weight:600;text-decoration:underline;">Download File</a></div>';
        setTimeout(function(){ toast.remove(); }, 60000);
    }

    function showError(toast, msg) {
        toast.style.borderLeftColor = '#EF4444';
        toast.innerHTML = '<div style="width:20px;min-width:20px;height:20px;background:#EF4444;border-radius:50%;display:flex;align-items:center;justify-content:center;"><svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M3 3l6 6M9 3l-6 6" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg></div><div><strong style="color:#EF4444;">Export gagal</strong><br><span style="color:#6B7280;font-size:12px;">' + (msg || 'Terjadi kesalahan') + '</span></div>';
        setTimeout(function(){ toast.remove(); }, 8000);
    }

    function triggerDownload(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'export.xlsx';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (!document.getElementById('export-toast-container')) {
            var container = document.createElement('div');
            container.id = 'export-toast-container';
            container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:10px;';
            document.body.appendChild(container);
        }

        if (!document.getElementById('export-spin-style')) {
            var style = document.createElement('style');
            style.id = 'export-spin-style';
            style.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
            document.head.appendChild(style);
        }

        document.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-export]');
            if (!btn) return;
            e.preventDefault();

            const url = btn.getAttribute('data-export') || btn.href;
            if (!url) return;

            const toast = showLoading();

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => {
                const contentType = response.headers.get('content-type') || '';

                // Jika response adalah JSON (queue export)
                if (contentType.includes('application/json')) {
                    return response.json().then(data => {
                        if (!data.job_id) {
                            showError(toast, data.message || 'Terjadi kesalahan');
                            return;
                        }
                        // Poll status
                        const poll = setInterval(() => {
                            fetch('/export-jobs/' + data.job_id + '/status', {
                                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                            })
                            .then(r => r.json())
                            .then(s => {
                                if (s.status === 'completed') {
                                    clearInterval(poll);
                                    showSuccess(toast, '/export-jobs/' + data.job_id + '/download');
                                } else if (s.status === 'failed') {
                                    clearInterval(poll);
                                    showError(toast, s.error || 'Terjadi kesalahan');
                                }
                            })
                            .catch(() => {});
                        }, 2000);
                    });
                }

                // Jika response adalah file (sync export) — trigger download langsung
                if (response.ok) {
                    const disposition = response.headers.get('content-disposition') || '';
                    let filename = 'export.xlsx';
                    const match = disposition.match(/filename="?([^";\n]+)"?/);
                    if (match) filename = match[1];

                    return response.blob().then(blob => {
                        triggerDownload(blob, filename);
                        toast.style.borderLeftColor = '#10B981';
                        toast.innerHTML = '<div style="width:20px;min-width:20px;height:20px;background:#10B981;border-radius:50%;display:flex;align-items:center;justify-content:center;"><svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><div><strong style="color:#10B981;">Download berhasil!</strong></div>';
                        setTimeout(() => toast.remove(), 3000);
                    });
                }

                throw new Error('Export failed');
            })
            .catch(err => {
                showError(toast, err.message || 'Terjadi kesalahan');
            });
        });
    });
    </script>

    <!-- Import Form Handler -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('form[action*="process-import"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var frm = this;

                var toast = showToast('<div style="width:20px;min-width:20px;height:20px;border:3px solid #e5e7eb;border-top-color:#6366F1;border-radius:50%;animation:spin 0.8s linear infinite;"></div><div><strong>Import sedang diproses...</strong><br><span style="color:#6B7280;font-size:12px;">Mohon tunggu sebentar, data sedang diproses di background.</span></div>');

                var formData = new FormData(frm);
                fetch(frm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    var ct = response.headers.get('content-type') || '';
                    if (ct.indexOf('application/json') !== -1) {
                        return response.json();
                    }
                    throw new Error('Unexpected response');
                })
                .then(function(data) {
                    if (data.success) {
                        toast.style.borderLeftColor = '#10B981';
                        toast.innerHTML = '<div style="width:20px;min-width:20px;height:20px;background:#10B981;border-radius:50%;display:flex;align-items:center;justify-content:center;"><svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><div><strong style="color:#10B981;">Import diproses!</strong><br><span style="color:#6B7280;font-size:12px;">' + (data.message || 'Anda akan dialihkan...') + '</span></div>';
                        setTimeout(function() { toast.remove(); window.location.href = data.redirect || '/sales/list'; }, 3000);
                    } else {
                        showError(toast, data.message || 'Import gagal');
                    }
                })
                .catch(function(err) {
                    showError(toast, err.message || 'Terjadi kesalahan');
                });
            });
        });
    });
    </script>
    
    <!-- Date Format Handler -->
    <script src="{{ asset('js/date-format.js') }}"></script>
    
    @stack('scripts')
</body>
</html>