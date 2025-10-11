<?php
// Set timezone for Malaysia
date_default_timezone_set("Asia/Kuala_Lumpur");

// Only start session if not already started and headers haven't been sent
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
include_once(__DIR__ . '/db.php');

// 获取当前脚本的目录深度，以便正确设置相对路径
$root_path = "";
$current_dir = dirname($_SERVER['PHP_SELF']);
$depth = substr_count($current_dir, '/');
if (strpos($current_dir, '/admin') !== false || 
    strpos($current_dir, '/doctor') !== false || 
    strpos($current_dir, '/patient') !== false) {
    $root_path = "../";
}

// 获取当前页面的完整URL
$current_url = $_SERVER['PHP_SELF'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green Life Dental Clinic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #10b981;
            --accent-color: #f59e0b;
            --light-bg: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --navbar-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --gradient-bg: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #1e40af 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
            padding-top: 85px;
            line-height: 1.6;
            position: relative;
            z-index: 1;
            overflow-x: hidden;
        }

        /* Modern Navbar Design */
        .navbar {
            background: var(--light-bg);
            padding: 0;
            box-shadow: var(--navbar-shadow);
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            z-index: 9999;
            border-bottom: 1px solid rgba(37, 99, 235, 0.1);
        }

        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            position: relative;
            min-height: 70px;
        }

        /* Desktop layout: brand, nav, user-info */
        @media (min-width: 981px) {
            .navbar-container {
                justify-content: flex-start;
                gap: 20px;
            }
            
            .navbar-brand {
                order: 1;
                flex-shrink: 0;
            }
            
            .navbar-nav {
                order: 2;
                flex: 1;
                justify-content: center;
                max-width: none;
            }
            
            .user-info {
                order: 3;
                flex-shrink: 0;
                margin-left: auto;
            }
            
            .user-info-placeholder {
                order: 3;
                margin-left: auto;
            }
        }

        /* Clinic Logo/Brand */
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .navbar-brand:hover {
            color: var(--primary-color);
            text-decoration: none;
        }

        .navbar-brand .brand-icon {
            background: var(--gradient-bg);
            color: white;
            padding: 8px;
            border-radius: 12px;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .navbar-brand .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
            justify-content: center;
        }

        .navbar-brand .brand-main {
            font-size: 1.2rem;
            color: var(--text-primary);
        }

        .navbar-brand .brand-sub {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Navigation Menu */
        .navbar-nav {
            list-style: none;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-end;
            gap: 2px;
            margin: 0;
            padding: 0;
            flex-wrap: nowrap;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            max-width: calc(100vw - 380px);
            padding-right: 10px;
        }

        /* Desktop navigation styles */
        @media (min-width: 981px) {
            .navbar-nav {
                justify-content: flex-start;
                max-width: none;
                overflow-x: visible;
                padding-right: 0;
                gap: 4px;
            }
        }

        .navbar-nav::-webkit-scrollbar {
            display: none;
        }

        /* 當導航項目過多時，添加滾動提示 */
        .navbar-nav::after {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 30px;
            background: linear-gradient(to left, rgba(248, 250, 252, 1) 0%, rgba(248, 250, 252, 0.9) 50%, transparent 100%);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }

        .navbar-nav.scrollable::after {
            opacity: 1;
        }

        .navbar-nav li {
            position: relative;
            flex-shrink: 0;
        }

        .navbar-nav li a {
            text-decoration: none;
            font-size: 0.60rem;
            font-weight: 500;
            color: var(--text-primary);
            padding: 6px 8px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
            position: relative;
            white-space: nowrap;
            min-width: fit-content;
            transition: all 0.2s ease;
        }

        .navbar-nav li a i {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .navbar-nav li a:hover {
            background: rgba(37, 99, 235, 0.08);
            color: var(--primary-color);
            text-decoration: none;
        }

        .navbar-nav li a.active {
            background: var(--gradient-bg);
            color: white;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
        }

        .navbar-nav li a.active i {
            opacity: 1;
        }

        .navbar-nav li a.logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            margin-left: 6px;
        }

        .navbar-nav li a.logout-btn:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
        }

        /* Modern User Info Design */
        /* Simple Card Style User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease;
            cursor: pointer;
            min-width: 180px;
            height: 60px;
        }
        
        /* Mobile clinic title for non-logged users */
        .mobile-clinic-title {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            flex: 1;
            margin: 0 60px;
        }
        
        .mobile-clinic-title .clinic-name {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-primary);
            line-height: 1.2;
        }
        
        .mobile-clinic-title .clinic-subtitle {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Placeholder for consistent layout */
        .user-info-placeholder {
            min-width: 180px;
            height: 60px;
            flex-shrink: 0;
        }

        .user-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.15);
            border-color: #2563eb;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            background: #f3f4f6;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #374151;
            font-weight: 600;
            font-size: 1rem;
            border: 2px solid #e5e7eb;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .user-info:hover .user-avatar {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
            min-width: 0;
            justify-content: center;
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: #111827;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 110px;
        }

        .user-role {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: capitalize;
            font-weight: 500;
        }

        /* Status indicator */
        .user-status {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-left: auto;
            flex-shrink: 0;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            background: #22c55e;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .status-text {
            font-size: 0.7rem;
            color: #059669;
            font-weight: 500;
        }
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .user-info {
                min-width: 140px;
                gap: 8px;
                padding: 6px 12px;
                height: 48px;
            }
            
            .user-info-placeholder {
                min-width: 140px;
                height: 48px;
            }
            
            .user-avatar {
                width: 36px;
                height: 36px;
                font-size: 0.85rem;
                border-radius: 10px;
            }
            
            .navbar-brand .brand-icon {
                width: 36px;
                height: 36px;
                font-size: 1.2rem;
                border-radius: 10px;
            }
            
            .user-name {
                font-size: 0.85rem;
                max-width: 80px;
            }
            
            .status-text {
                display: none;
            }
        }



        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-primary);
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .menu-toggle:hover {
            background: rgba(37, 99, 235, 0.08);
            color: var(--primary-color);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .navbar-container {
                padding: 0 15px;
                max-width: 100%;
                justify-content: space-between; /* 回到原來的佈局 */
                gap: 10px;
            }
            
            .navbar-nav {
                max-width: calc(100vw - 320px);
                gap: 1px;
                justify-content: flex-end; /* 回到右對齊 */
            }
            
            .navbar-nav li a {
                font-size: 0.75rem;
                padding: 7px 8px;
                gap: 4px;
            }
            
            .user-info {
                order: initial;
                margin-left: 0;
            }
            
            .user-info-placeholder {
                order: initial;
                margin-left: 0;
            }
        }

        @media (max-width: 1100px) {
            .navbar-container {
                justify-content: space-between;
                gap: 8px;
            }
            
            .navbar-nav {
                max-width: calc(100vw - 300px);
                gap: 0px;
                justify-content: flex-end;
            }
            
            .navbar-nav li a {
                font-size: 0.7rem;
                padding: 6px 7px;
                gap: 3px;
            }
            
            .user-info {
                order: initial;
                margin-left: 0;
            }
            
            .user-info-placeholder {
                order: initial;
                margin-left: 0;
            }
        }

        @media (max-width: 992px) {
            .navbar-container {
                justify-content: space-between;
                gap: 5px;
            }
            
            .navbar-brand .brand-text {
                display: none;
            }
            
            .user-details {
                display: flex;
            }
            
            .navbar-nav {
                max-width: calc(100vw - 260px);
                justify-content: flex-end;
            }
            
            .navbar-nav li a {
                font-size: 0.65rem;
                padding: 5px 6px;
                gap: 2px;
            }
            
            .navbar-nav li a span.nav-text {
                display: none;
            }
            
            .user-info {
                order: initial;
                margin-left: 0;
            }
            
            .user-info-placeholder {
                order: initial;
                margin-left: 0;
            }
        }

        /* 當屏幕太小時，強制使用移動端菜單 */
        @media (max-width: 980px) {
            .navbar-container {
                padding: 0 70px 0 15px; /* 右側留出足夠空間給汉堡菜单 */
                justify-content: space-between;
                position: relative;
            }
            
            .menu-toggle {
                display: block;
                order: 3;
                flex-shrink: 0;
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
                z-index: 10001;
            }

            .user-info {
                order: 2;
                padding: 8px 14px;
                flex-shrink: 0;
                gap: 12px;
                max-width: calc(100% - 90px); /* 确保不会与汉堡菜单重叠 */
            }

            .navbar-nav {
                display: none;
                position: absolute;
                top: 75px; /* 調整頂部位置避免遮擋 */
                right: 15px;
                min-width: 300px; /* 增加寬度給更多空間 */
                max-width: 90vw;
                max-height: calc(100vh - 100px); /* 調整最大高度 */
                background: white;
                border-radius: 12px;
                padding: 5px 0 15px 0; /* 大幅減少頂部padding到只有5px */
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
                border: 1px solid rgba(0, 0, 0, 0.05);
                flex-direction: column;
                align-items: stretch;
                gap: 1px; /* 減少間距節省空間 */
                overflow-x: visible;
                overflow-y: scroll; /* 強制顯示滾動條 */
                scrollbar-width: auto; /* Firefox 顯示滾動條 */
                scrollbar-color: #64748b #e2e8f0; /* Firefox 滾動條顏色 */
                z-index: 10000;
                padding-right: 0;
                /* 加強觸摸滾動體驗 */
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
                touch-action: pan-y;
            }

            /* Webkit 滾動條樣式（Chrome, Safari, Edge） - 加強版本 */
            .navbar-nav::-webkit-scrollbar {
                width: 12px; /* 增加寬度讓滾動更容易 */
            }

            .navbar-nav::-webkit-scrollbar-track {
                background: rgba(203, 213, 225, 0.5);
                border-radius: 6px;
                margin: 5px 0;
            }

            .navbar-nav::-webkit-scrollbar-thumb {
                background: linear-gradient(180deg, #64748b, #475569);
                border-radius: 6px;
                border: 2px solid white;
                transition: all 0.3s ease;
                min-height: 30px; /* 確保滾動條有足夠的高度 */
            }

            .navbar-nav::-webkit-scrollbar-thumb:hover {
                background: linear-gradient(180deg, #475569, #334155);
                transform: scale(1.1);
            }

            .navbar-nav::-webkit-scrollbar-thumb:active {
                background: linear-gradient(180deg, #334155, #1e293b);
            }

            .navbar-nav.show {
                display: flex;
            }

            .navbar-nav li {
                margin: 0 15px;
                flex-shrink: 0; /* 防止選項被壓縮 */
            }

            /* 第一個菜單項目額外的頂部邊距 */
            .navbar-nav li:first-child {
                margin-top: 12px; /* 增加頂部邊距確保Dashboard完全可見 */
            }

            .navbar-nav li a {
                padding: 14px 16px; /* 增加padding讓點擊區域更大 */
                border-radius: 8px;
                font-size: 0.9rem;
                width: 100%;
                justify-content: flex-start;
                gap: 10px; /* 增加圖標和文字間距 */
                min-height: 48px; /* 增加最小高度確保觸摸友好 */
            }
            
            .navbar-nav li a span.nav-text {
                display: inline;
            }

            .navbar-nav li a.logout-btn {
                margin: 8px 0 0 0;
                border-top: 1px solid rgba(0, 0, 0, 0.05);
                padding-top: 16px;
                margin-top: 12px; /* 增加與其他選項的間距 */
            }

            .user-info {
                order: 2;
                padding: 8px 14px;
                flex-shrink: 0;
                gap: 12px;
                max-width: calc(100% - 90px); /* 确保不会与汉堡菜单重叠 */
            }

            body {
                padding-top: 90px;
            }
        }

        @media (max-width: 768px) {
            .navbar-container {
                padding: 0 70px 0 15px; /* 右側留出足夠空間給汉堡菜单 */
                flex-wrap: nowrap;
                justify-content: space-between;
                position: relative;
            }

            .menu-toggle {
                display: block;
                order: 3;
                flex-shrink: 0;
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
                z-index: 10001;
            }
            
            /* Show mobile clinic title for non-logged users */
            .navbar-container.show-mobile-title .mobile-clinic-title {
                display: flex;
            }
            
            /* Hide brand text on mobile when showing mobile title */
            .navbar-container.show-mobile-title .navbar-brand .brand-text {
                display: none;
            }

            .navbar-nav {
                display: none;
                position: absolute;
                top: 75px; /* 稍微增加距離 */
                right: 15px;
                min-width: 300px; /* 增加寬度給更多空間 */
                max-width: 90vw;
                max-height: calc(100vh - 100px); /* 調整最大高度 */
                background: white;
                border-radius: 12px;
                padding: 5px 0 15px 0; /* 大幅減少頂部padding */
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
                border: 1px solid rgba(0, 0, 0, 0.05);
                flex-direction: column;
                align-items: stretch;
                gap: 1px; /* 減少間距 */
                overflow-x: visible;
                overflow-y: scroll; /* 強制顯示滾動條 */
                scrollbar-width: auto;
                scrollbar-color: #64748b #e2e8f0;
                z-index: 10000;
                padding-right: 0;
                /* 加強觸摸滾動體驗 */
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
                touch-action: pan-y;
            }

            /* Webkit 滾動條樣式 - 加強版本 */
            .navbar-nav::-webkit-scrollbar {
                width: 12px;
            }

            .navbar-nav::-webkit-scrollbar-track {
                background: rgba(203, 213, 225, 0.5);
                border-radius: 6px;
                margin: 5px 0;
            }

            .navbar-nav::-webkit-scrollbar-thumb {
                background: linear-gradient(180deg, #64748b, #475569);
                border-radius: 6px;
                border: 2px solid white;
                transition: all 0.3s ease;
                min-height: 30px;
            }

            .navbar-nav::-webkit-scrollbar-thumb:hover {
                background: linear-gradient(180deg, #475569, #334155);
                transform: scale(1.1);
            }

            .navbar-nav::-webkit-scrollbar-thumb:active {
                background: linear-gradient(180deg, #334155, #1e293b);
            }

            .navbar-nav.show {
                display: flex;
            }

            .navbar-nav li {
                margin: 0 15px;
                flex-shrink: 0;
            }

            /* 第一個菜單項目額外的頂部邊距 */
            .navbar-nav li:first-child {
                margin-top: 12px; /* 增加頂部邊距確保第一個選項完全可見 */
            }

            .navbar-nav li a {
                padding: 14px 16px; /* 增加padding讓點擊區域更大 */
                border-radius: 8px;
                font-size: 0.9rem;
                width: 100%;
                justify-content: flex-start;
                gap: 10px; /* 增加圖標和文字間距 */
                min-height: 48px; /* 增加最小高度確保觸摸友好 */
            }
            
            .navbar-nav li a span.nav-text {
                display: inline;
            }

            .navbar-nav li a.logout-btn {
                margin: 8px 0 0 0;
                border-top: 1px solid rgba(0, 0, 0, 0.05);
                padding-top: 16px;
                margin-top: 12px;
            }

            .user-info {
                order: 2;
                padding: 8px 14px;
                flex-shrink: 0;
                gap: 12px;
                max-width: calc(100% - 90px); /* 确保不会与汉堡菜单重叠 */
                min-width: 140px;
            }
            
            /* 调整移动端诊所标题的边距，避免与汉堡菜单重叠 */
            .mobile-clinic-title {
                margin: 0 20px;
                max-width: calc(100% - 140px); /* 为汉堡菜单和品牌图标留出空间 */
            }

            .user-avatar {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }

            .user-name {
                font-size: 0.85rem;
                max-width: 100px;
            }

            body {
                padding-top: 90px;
            }
        }

        @media (max-width: 480px) {
            .navbar-container {
                padding: 0 65px 0 15px; /* 为小屏幕的汉堡菜单留出足够空间 */
            }
            
            .navbar-brand .brand-main {
                font-size: 1rem;
            }

            .user-info-placeholder {
                min-width: 120px;
                height: 40px;
            }
            
            .mobile-clinic-title {
                margin: 0 15px;
                max-width: calc(100% - 120px); /* 为汉堡菜单和品牌图标留出更多空间 */
            }
            
            .mobile-clinic-title .clinic-name {
                font-size: 0.9rem;
            }
            
            .mobile-clinic-title .clinic-subtitle {
                font-size: 0.65rem;
            }

            .navbar-nav {
                right: 10px;
                min-width: 320px; /* 增加寬度給更多空間 */
                top: 70px; /* 調整頂部位置給更多空間 */
                max-height: calc(100vh - 90px); /* 增加可用高度 */
                z-index: 10001;
                padding: 2px 0 10px 0; /* 進一步減少padding節省空間 */
            }

            /* 確保菜單項目在小型裝置上更緊密但仍可點擊 */
            .navbar-nav li a {
                padding: 8px 16px; /* 進一步減少padding節省空間 */
                font-size: 0.8rem; /* 稍微調小字體以節省空間 */
                min-height: 38px; /* 減少高度但保持適當的點擊區域 */
                gap: 6px;
            }

            .user-info {
                padding: 6px 10px;
                gap: 10px;
                min-width: 120px;
                max-width: calc(100% - 80px); /* 在小屏幕上给汉堡菜单更多空间 */
            }

            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }

            .user-name {
                font-size: 0.8rem;
                max-width: 80px;
            }

            .user-role {
                font-size: 0.65rem;
                padding: 1px 6px;
            }
            
            body {
                padding-top: 85px;
            }
        }

        /* Animation for mobile menu */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .navbar-nav.show {
            animation: slideDown 0.3s ease;
        }

        /* Mobile menu scroll indicators - 外部提示版本 */
        @media (max-width: 980px) {
            .navbar-container::before {
                content: '↑ 上滾查看更多';
                position: fixed;
                top: 85px; /* 進一步調整位置避免遮擋菜單頂部 */
                right: 50%; /* 置中顯示 */
                transform: translateX(50%); /* 精確置中 */
                width: 120px;
                height: 28px;
                background: rgba(37, 99, 235, 0.95);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 11px;
                font-weight: 600;
                border-radius: 20px; /* 更圓潤的設計 */
                z-index: 10002;
                opacity: 0;
                transition: all 0.3s ease;
                pointer-events: none;
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
            }

            .navbar-container.show-top-indicator::before {
                opacity: 1;
                transform: translateX(50%) translateY(-2px); /* 輕微上揚 */
            }

            .navbar-container::after {
                content: '↓ 下滾查看更多';
                position: fixed;
                bottom: 30px; /* 調整底部位置 */
                right: 50%; /* 置中顯示 */
                transform: translateX(50%); /* 精確置中 */
                width: 120px;
                height: 28px;
                background: rgba(37, 99, 235, 0.95);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 11px;
                font-weight: 600;
                border-radius: 20px; /* 更圓潤的設計 */
                z-index: 10002;
                opacity: 0;
                transition: all 0.3s ease;
                pointer-events: none;
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
            }

            .navbar-container.show-bottom-indicator::after {
                opacity: 1;
                transform: translateX(50%) translateY(2px); /* 輕微下沈 */
            }
        }

        /* 小型裝置的額外優化 */
        @media (max-width: 480px) {
            .navbar-container::before {
                width: 110px;
                height: 22px;
                font-size: 9px;
                top: 70px; /* 調整與菜單頂部對齊 */
            }

            .navbar-container::after {
                width: 110px;
                height: 22px;
                font-size: 9px;
                bottom: 20px; /* 調整底部位置 */
            }
        }

        /* Loading state */
        .navbar-nav li a.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .navbar-nav li a.loading::after {
            content: "";
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Clinic Brand/Logo -->
            <a href="<?php 
                // 根据用户角色动态设置链接地址
                if (isset($_SESSION['admin_id'])) {
                    echo '/Dental_Clinic/admin/dashboard.php';
                } elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'doctor') {
                    echo '/Dental_Clinic/doctor/dashboard.php';
                } else {
                    // 未登录用户或患者都跳转到首页
                    echo '/Dental_Clinic/index.php';
                }
            ?>" class="navbar-brand">
                <div class="brand-icon">
                    <i class="bi bi-heart-pulse-fill"></i>
                </div>
                <div class="brand-text">
                    <span class="brand-main">Green Life</span>
                    <span class="brand-sub">Dental Clinic</span>
                </div>
            </a>

            <!-- Mobile Clinic Title (for non-logged users on mobile) -->
            <div class="mobile-clinic-title">
                <span class="clinic-name">Green Life</span>
                <span class="clinic-subtitle">Dental Clinic</span>
            </div>

            <!-- Simple User Info Card (for logged in users) -->
            <?php if (isset($_SESSION['admin_id']) || isset($_SESSION['user_id'])): ?>
                <a href="<?php 
                    if (isset($_SESSION['admin_id'])) {
                        echo '/Dental_Clinic/admin/dashboard.php';
                    } elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'doctor') {
                        echo '/Dental_Clinic/doctor/dashboard.php';
                    } else {
                        echo '/Dental_Clinic/patient/dashboard.php';
                    }
                ?>" class="user-info">
                    <div class="user-avatar">
                        <?php 
                        if (isset($_SESSION['admin_id'])) {
                            echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 2));
                        } elseif (isset($_SESSION['user_name'])) {
                            echo strtoupper(substr($_SESSION['user_name'], 0, 2));
                        } else {
                            echo 'U';
                        }
                        ?>
                    </div>
                    <div class="user-details">
                        <span class="user-name">
                            <?php 
                            if (isset($_SESSION['admin_id'])) {
                                echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
                            } else {
                                echo htmlspecialchars($_SESSION['user_name'] ?? 'User');
                            }
                            ?>
                        </span>
                        <span class="user-role">
                            <?php 
                            if (isset($_SESSION['admin_id'])) {
                                echo 'Administrator';
                            } elseif (isset($_SESSION['user_role'])) {
                                echo ucfirst(htmlspecialchars($_SESSION['user_role']));
                            } else {
                                echo 'User';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="user-status">
                        <div class="status-dot"></div>
                        <span class="status-text">Online</span>
                    </div>
                </a>
            <?php endif; ?>

            <!-- Placeholder for consistent layout when not logged in -->
            <?php if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])): ?>
                <div class="user-info-placeholder"></div>
            <?php endif; ?>

            <!-- Mobile Menu Toggle -->
            <button class="menu-toggle" type="button">
                <i class="bi bi-list"></i>
            </button>

            <!-- Navigation Menu -->
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['admin_id']) && $_SESSION['user_role'] === 'admin') { ?>
                    <!-- Admin Navigation Menu -->
                    <li><a href="/Dental_Clinic/admin/dashboard.php" <?php echo (strpos($current_url, 'dashboard.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-speedometer2"></i><span class="nav-text">Dashboard</span>
                    </a></li>
                    <li><a href="/Dental_Clinic/admin/manage_users.php" <?php echo (strpos($current_url, 'manage_users.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-people"></i><span class="nav-text">Users</span>
                    </a></li>
                    <li><a href="/Dental_Clinic/admin/manage_doctors.php" <?php echo (strpos($current_url, 'manage_doctors.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-person-badge"></i><span class="nav-text">Dentists</span>
                    </a></li>
                    <li><a href="/Dental_Clinic/admin/manage_appointments.php" <?php echo (strpos($current_url, 'manage_appointments.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-calendar-check"></i><span class="nav-text">Appointments</span>
                    </a></li>
                    <li><a href="/Dental_Clinic/admin/admin_set_unavailable.php" <?php echo (strpos($current_url, 'admin_set_unavailable.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-clock-history"></i><span class="nav-text">Availability</span>
                    </a></li>
                    <li><a href="/Dental_Clinic/admin/patient_records.php" <?php echo (strpos($current_url, 'patient_records.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-journal-medical"></i><span class="nav-text">Records</span>
                    </a></li>
                    <li><a href="/Dental_Clinic/admin/patient_history.php" <?php echo (strpos($current_url, 'patient_history.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-file-medical-fill"></i><span class="nav-text">History</span>
                    </a></li>
                    <li><a href="/Dental_Clinic/admin/manage_service.php" <?php echo (strpos($current_url, 'manage_service.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-capsule"></i><span class="nav-text">Service</span>
                    </a></li>
                    <li><a href="/Dental_Clinic/admin/billing.php" <?php echo (strpos($current_url, 'billing.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-credit-card"></i><span class="nav-text">Billing</span>
                    </a></li>
                    <li><a href="/Dental_Clinic/admin/reports.php" <?php echo (strpos($current_url, 'reports.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-bar-chart"></i><span class="nav-text">Reports</span>
                    </a></li>
                    <li><a href="/Dental_Clinic/admin/messages.php" <?php echo (strpos($current_url, 'messages.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-chat-dots"></i><span class="nav-text">Messages</span>
                    </a></li>
                    <li><a href="/Dental_Clinic/admin/system_logs.php" <?php echo (strpos($current_url, 'system_logs.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-clipboard-data-fill"></i><span class="nav-text">System Activity</span>
                    </a></li>
                    <li><a href="/Dental_Clinic/admin/manage_reviews.php" <?php echo (strpos($current_url, 'manage_reviews.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-star-fill"></i><span class="nav-text">Manage Reviews</span>
                    </a></li>
                    <!-- <li><a href="/Dental_Clinic/admin/clinic_settings.php" <?php echo (strpos($current_url, 'clinic_settings.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-gear"></i>Settings
                    </a></li> -->
                    <li><a href="/Dental_Clinic/admin/logout.php" class="logout-btn">
                        <i class="bi bi-box-arrow-right"></i>Logout
                    </a></li>

                <?php } elseif (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'doctor') { ?>
                    <!-- Doctor Navigation Menu -->
                    <li><a href="/Dental_Clinic/doctor/dashboard.php" <?php echo (strpos($current_url, 'dashboard.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-speedometer2"></i>Dashboard
                    </a></li>
                    <li><a href="/Dental_Clinic/doctor/patient_records.php" <?php echo (strpos($current_url, 'patient_records.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-journal-medical"></i>Patient Records
                    </a></li>
                    <li><a href="/Dental_Clinic/doctor/patient_history.php" <?php echo (strpos($current_url, 'patient_history.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="fas fa-file-medical"></i>Add Medical Record
                    </a></li>
                    <li><a href="/Dental_Clinic/doctor/manage_appointments.php" <?php echo (strpos($current_url, 'manage_appointments.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-calendar-check"></i>Appointments
                    </a></li>
                    <li><a href="/Dental_Clinic/doctor/doctor_profile.php" <?php echo (strpos($current_url, 'doctor_profile.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-person-circle"></i>My Profile
                    </a></li>
                    <li><a href="/Dental_Clinic/doctor/my_reviews.php" <?php echo (strpos($current_url, 'my_reviews.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-star-fill"></i>My Reviews
                    </a></li>
                    <!-- <li><a href="/Dental_Clinic/doctor/messages.php" <?php echo (strpos($current_url, 'messages.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-chat-dots"></i>Messages
                    </a></li> -->
                    <li><a href="/Dental_Clinic/doctor/logout.php" class="logout-btn">
                        <i class="bi bi-box-arrow-right"></i>Logout
                    </a></li>

                <?php } elseif (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'patient') { ?>
                    <!-- Patient Navigation Menu -->
                    <li><a href="/Dental_Clinic/patient/dashboard.php" <?php echo (strpos($current_url, 'dashboard.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-speedometer2"></i>Dashboard
                    </a></li>
                    <li><a href="/Dental_Clinic/patient/book_appointment.php" <?php echo (strpos($current_url, 'book_appointment.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-calendar-plus"></i>Book Appointment
                    </a></li>
                    <li><a href="/Dental_Clinic/patient/my_appointments.php" <?php echo (strpos($current_url, 'my_appointments.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-calendar-check"></i>My Appointments
                    </a></li>
                    <li><a href="/Dental_Clinic/patient/patient_history.php" <?php echo (strpos($current_url, 'patient_history.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-file-medical"></i>Medical History
                    </a></li>
                    <li><a href="/Dental_Clinic/patient/billing.php" <?php echo (strpos($current_url, 'billing.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-receipt"></i>Billing
                    </a></li>
                    <li><a href="/Dental_Clinic/patient/message.php" <?php echo (strpos($current_url, 'message.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-chat-dots"></i>Messages
                    </a></li>
                    <li><a href="/Dental_Clinic/all_doctors.php" <?php echo (strpos($current_url, 'all_doctors.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="fas fa-user-md"></i>Find Doctors
                    </a></li>
                    <li><a href="/Dental_Clinic/patient/my_reviews.php" <?php echo (strpos($current_url, 'my_reviews.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-star-fill"></i>My Reviews
                    </a></li>
                    <li><a href="/Dental_Clinic/patient/my_profile.php" <?php echo (strpos($current_url, 'my_profile.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-person-circle"></i>My Profile
                    </a></li>
                    <li><a href="/Dental_Clinic/patient/logout.php" class="logout-btn">
                        <i class="bi bi-box-arrow-right"></i>Logout
                    </a></li>

                <?php } elseif (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) { ?>
                    <!-- Public Navigation Menu -->
                    <li><a href="/Dental_Clinic/index.php" <?php echo (strpos($current_url, 'index.php') !== false || $current_url === '/Dental_Clinic/') ? 'class="active"' : ''; ?>>
                        <i class="bi bi-house"></i>Home
                    </a></li>
                    <li><a href="/Dental_Clinic/all_doctors.php" <?php echo (strpos($current_url, 'all_doctors.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-people"></i>Our Doctors
                    </a></li>
                    <li><a href="/Dental_Clinic/book_appointment.php" <?php echo (strpos($current_url, 'book_appointment.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-calendar"></i> Book Appointment
                    </a></li>
                    <li><a href="/Dental_Clinic/patient/register.php" <?php echo (strpos($current_url, 'patient/register.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-person-plus"></i>Patient Register
                    </a></li>
                    <li><a href="/Dental_Clinic/patient/login.php" <?php echo (strpos($current_url, 'patient/login.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-box-arrow-in-right"></i>Patient Login
                    </a></li>
                    <li><a href="/Dental_Clinic/doctor/login.php" <?php echo (strpos($current_url, 'doctor/login.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-person-badge"></i>Doctor Login
                    </a></li>
                    <li><a href="/Dental_Clinic/admin/login.php" <?php echo (strpos($current_url, 'admin/login.php') !== false) ? 'class="active"' : ''; ?>>
                        <i class="bi bi-shield-lock"></i>Admin Login
                    </a></li>
                <?php } ?>
            </ul>
        </div>
    </nav>

    <script>
document.addEventListener("DOMContentLoaded", function() {
    const toggle = document.querySelector('.menu-toggle');
    const navMenu = document.querySelector('.navbar-nav');
    const toggleIcon = toggle ? toggle.querySelector('i') : null;
    const navbarContainer = document.querySelector('.navbar-container');

    // 检查是否需要显示移动端诊所标题
    function checkMobileTitle() {
        const isLoggedIn = <?php echo (isset($_SESSION['admin_id']) || isset($_SESSION['user_id'])) ? 'true' : 'false'; ?>;
        const isMobile = window.innerWidth <= 768;
        
        if (!isLoggedIn && isMobile && navbarContainer) {
            navbarContainer.classList.add('show-mobile-title');
        } else if (navbarContainer) {
            navbarContainer.classList.remove('show-mobile-title');
        }
    }

    // 初始检查
    checkMobileTitle();

    // 窗口大小改变时重新检查
    window.addEventListener('resize', checkMobileTitle);

    // 檢查移動端菜單滾動狀態 - 外部指示器版本
    function checkMobileMenuScroll() {
        const navbarContainer = document.querySelector('.navbar-container');
        
        if (navMenu && navMenu.classList.contains('show') && navbarContainer) {
            const scrollTop = navMenu.scrollTop;
            const scrollHeight = navMenu.scrollHeight;
            const clientHeight = navMenu.clientHeight;
            const scrollBottom = scrollHeight - clientHeight;
            
            console.log('滾動檢測:', {
                scrollTop: scrollTop,
                scrollHeight: scrollHeight,
                clientHeight: clientHeight,
                canScroll: scrollHeight > clientHeight
            });
            
            // 更寬鬆的滾動檢測
            const isAtTop = scrollTop <= 10;
            const isAtBottom = scrollTop >= (scrollBottom - 10);
            const canScroll = scrollHeight > clientHeight;
            
            // 只有當內容可以滾動時才顯示指示器
            if (canScroll) {
                console.log('內容可以滾動, isAtTop:', isAtTop, 'isAtBottom:', isAtBottom);
                
                if (isAtTop) {
                    navbarContainer.classList.remove('show-top-indicator');
                } else {
                    navbarContainer.classList.add('show-top-indicator');
                }
                
                if (isAtBottom) {
                    navbarContainer.classList.remove('show-bottom-indicator');
                } else {
                    navbarContainer.classList.add('show-bottom-indicator');
                }
            } else {
                console.log('內容不需要滾動');
                // 如果內容不需要滾動，移除所有滾動指示器
                navbarContainer.classList.remove('show-top-indicator', 'show-bottom-indicator');
            }
        }
    }

    // 檢查導航是否需要滾動並添加滾動提示
    function checkNavScrollable() {
        if (window.innerWidth > 980 && navMenu) {
            const isScrollable = navMenu.scrollWidth > navMenu.clientWidth;
            if (isScrollable) {
                navMenu.classList.add('scrollable');
                console.log('Navigation is scrollable - showing scroll indicator');
            } else {
                navMenu.classList.remove('scrollable');
            }
        } else {
            navMenu.classList.remove('scrollable');
        }
    }

    // 初始檢查和窗口大小改變時檢查
    checkNavScrollable();
    window.addEventListener('resize', checkNavScrollable);

    if (toggle && navMenu && toggleIcon) {
        // Toggle mobile menu
        toggle.addEventListener('click', function() {
            navMenu.classList.toggle('show');
            
            // Toggle hamburger/close icon
            if (navMenu.classList.contains('show')) {
                toggleIcon.className = 'bi bi-x-lg';
                // 檢查移動端菜單是否需要滾動指示器
                setTimeout(() => {
                    checkMobileMenuScroll();
                    // 為移動端菜單添加滾動監聽器
                    navMenu.addEventListener('scroll', checkMobileMenuScroll);
                    
                    console.log('菜單已打開，添加滾動監聽器');
                }, 100);
            } else {
                toggleIcon.className = 'bi bi-list';
                // 移除滾動監聽器和指示器
                navMenu.removeEventListener('scroll', checkMobileMenuScroll);
                const navbarContainer = document.querySelector('.navbar-container');
                if (navbarContainer) {
                    navbarContainer.classList.remove('show-top-indicator', 'show-bottom-indicator');
                }
                console.log('菜單已關閉');
            }
        });

        // Close menu when clicking on a link (mobile only)
        document.querySelectorAll('.navbar-nav li a').forEach(link => {
            link.addEventListener('click', (e) => {
                if (window.innerWidth <= 980) {
                    navMenu.classList.remove('show');
                    toggleIcon.className = 'bi bi-list';
                    // 清理滾動監聽器和類名
                    navMenu.removeEventListener('scroll', checkMobileMenuScroll);
                    navMenu.classList.remove('scrollable-top', 'scrollable-bottom');
                }
                
                // Add loading state for navigation
                if (!link.classList.contains('logout-btn')) {
                    link.classList.add('loading');
                    setTimeout(() => {
                        link.classList.remove('loading');
                    }, 1000);
                }
            });
        });

        // Close menu when clicking outside (mobile only)
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 980) {
                if (!toggle.contains(e.target) && !navMenu.contains(e.target)) {
                    navMenu.classList.remove('show');
                    toggleIcon.className = 'bi bi-list';
                    // 清理滾動監聽器和類名
                    navMenu.removeEventListener('scroll', checkMobileMenuScroll);
                    navMenu.classList.remove('scrollable-top', 'scrollable-bottom');
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 980) {
                navMenu.classList.remove('show');
                toggleIcon.className = 'bi bi-list';
                // 清理滾動監聽器和指示器類名
                navMenu.removeEventListener('scroll', checkMobileMenuScroll);
                const navbarContainer = document.querySelector('.navbar-container');
                if (navbarContainer) {
                    navbarContainer.classList.remove('show-top-indicator', 'show-bottom-indicator');
                }
            }
        });
    }

    // Add smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Add notification dot for messages (if applicable)
    const messagesLink = document.querySelector('a[href*="messages.php"]');
    if (messagesLink) {
        // This could be enhanced to show actual unread message count
        // messagesLink.style.position = 'relative';
        // const dot = document.createElement('span');
        // dot.style.cssText = 'position: absolute; top: 8px; right: 8px; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; border: 2px solid white;';
        // messagesLink.appendChild(dot);
    }
});
</script>
</body>
</html>