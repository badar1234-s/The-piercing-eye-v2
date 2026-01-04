<?php
// ملف: ai_dashboard.php
session_start();
require 'db.php';
require 'ai_core.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$ai = new AICore($pdo);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>🤖 لوحة مراقبة الذكاء الاصطناعي</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0a0a0a; color: #00ff00; font-family: 'Courier New', monospace; }
        
        .dashboard {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #00ff00;
            padding-bottom: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #111;
            border: 1px solid #00ff00;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            color: #00ff00;
            margin: 10px 0;
        }
        
        .alerts-section {
            background: #111;
            border: 2px solid #ff0000;
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
        }
        
        .alert {
            background: #330000;
            border: 1px solid #ff0000;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .activity-log {
            background: #001100;
            border: 1px solid #00ff00;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            overflow-x: auto;
        }
        
        .risk-indicator {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            margin-right: 10px;
            font-weight: bold;
        }
        
        .risk-low { background: #003300; color: #00ff00; }
        .risk-medium { background: #333300; color: #ffff00; }
        .risk-high { background: #663300; color: #ff9900; }
        .risk-critical { background: #660000; color: #ff0000; }
        
        .ai-controls {
            display: flex;
            gap: 10px;
            margin: 20px 0;
        }
        
        .control-btn {
            background: #003300;
            color: #00ff00;
            border: 1px solid #00ff00;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .control-btn:hover {
            background: #00ff00;
            color: #000;
        }
        
        .prediction-card {
            background: #000033;
            border: 1px solid #0066ff;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="header">
            <h1>🤖 الذكاء الاصطناعي للمراقبة - نظام The Piercing Eye</h1>
            <p>👁️ النظام يراقب... النظام يحلل... النظام يتنبأ</p>
        </div>
        
        <?php
        // جلب الإنذارات النشطة
        $alerts = $ai->getRecentAlerts(5);
        if ($alerts) {
            echo '<div class="alerts-section">';
            echo '<h2>🚨 الإنذارات النشطة</h2>';
            foreach ($alerts as $alert) {
                $severity_class = 'risk-' . $alert['severity'];
                echo "<div class='alert'>
                        <span class='risk-indicator $severity_class'>" . strtoupper($alert['severity']) . "</span>
                        <strong>{$alert['alert_type']}</strong><br>
                        {$alert['alert_message']}<br>
                        <small>" . date('Y-m-d H:i:s', strtotime($alert['created_at'])) . "</small>
                      </div>";
            }
            echo '</div>';
        }
        ?>
        
        <div class="stats-grid">
            <?php
            // مستوى الخطورة الحالي
            $risk_level = $ai->getRiskLevel();
            $risk_class = 'risk-low';
            if ($risk_level >= 8) $risk_class = 'risk-critical';
            elseif ($risk_level >= 6) $risk_class = 'risk-high';
            elseif ($risk_level >= 4) $risk_class = 'risk-medium';
            
            echo "<div class='stat-card'>
                    <h3>⚠️ مستوى الخطورة الحالي</h3>
                    <div class='stat-number $risk_class'>" . number_format($risk_level, 1) . "/10</div>
                    <p>" . ($risk_level > 5 ? '🚨 انتباه: مستوى خطورة مرتفع' : '✅ الوضع طبيعي') . "</p>
                  </div>";
            
            // النشاط خلال 24 ساعة
            $activities = $ai->getActivitySummary(24);
            $total_activities = array_sum(array_column($activities, 'count'));
            
            echo "<div class='stat-card'>
                    <h3>📊 النشاط خلال 24 ساعة</h3>
                    <div class='stat-number'>$total_activities</div>
                    <p>" . count($activities) . " نوع مختلف من الأنشطة</p>
                  </div>";
            
            // الإنذارات النشطة
            $active_alerts = $ai->getRecentAlerts();
            $alert_count = count($active_alerts);
            
            echo "<div class='stat-card'>
                    <h3>🚨 الإنذارات النشطة</h3>
                    <div class='stat-number'>$alert_count</div>
                    <p>" . ($alert_count > 0 ? 'تحتاج للتدخل' : 'لا توجد إنذارات') . "</p>
                  </div>";
            
            // مصدر التحليل
            echo "<div class='stat-card'>
                    <h3>🤖 مصدر التحليل</h3>
                    <div class='stat-number'>" . htmlspecialchars($ai->settings['ai_model']) . "</div>
                    <p>" . (!empty($ai->settings['api_key']) ? '🌐 متصل بخدمة خارجية' : '💻 نظام محلي') . "</p>
                  </div>";
            ?>
        </div>
        
        <div class="ai-controls">
            <button class="control-btn" onclick="scanSystem()">🔍 مسح النظام</button>
            <button class="control-btn" onclick="trainAI()">🧠 تدريب الذكاء الاصطناعي</button>
            <button class="control-btn" onclick="generateReport()">📊 إنشاء تقرير</button>
            <button class="control-btn" onclick="clearAlerts()">🗑️ مسح الإنذارات</button>
        </div>
        
        <div style="margin: 30px 0;">
            <h2>📈 الأنشطة الأخيرة</h2>
            <?php
            $stmt = $pdo->query("
                SELECT al.*, u.username 
                FROM ai_activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC 
                LIMIT 10
            ");
            
            while ($log = $stmt->fetch()) {
                $data = json_decode($log['activity_data'], true);
                echo "<div class='activity-log'>
                        <strong>{$log['activity_type']}</strong> - 
                        المستخدم: " . ($log['username'] ?: 'مجهول') . " - 
                        مستوى الخطورة: <span class='risk-indicator risk-" . ($log['risk_level'] > 7 ? 'high' : 'medium') . "'>{$log['risk_level']}/10</span><br>
                        <small>البيانات: " . htmlspecialchars(print_r($data, true)) . "</small><br>
                        <small>" . $log['created_at'] . " - IP: {$log['ip_address']}</small>
                      </div>";
            }
            ?>
        </div>
        
        <div>
            <h2>🔮 تنبؤات الذكاء الاصطناعي</h2>
            <?php
            $stmt = $pdo->query("
                SELECT * FROM ai_predictions 
                WHERE is_active = 1 
                ORDER BY probability DESC 
                LIMIT 3
            ");
            
            while ($prediction = $stmt->fetch()) {
                echo "<div class='prediction-card'>
                        <h3>" . htmlspecialchars($prediction['prediction_type']) . "</h3>
                        <p>" . htmlspecialchars($prediction['prediction_data']) . "</p>
                        <div style='background: #003366; padding: 5px; border-radius: 5px; margin: 10px 0;'>
                            <div style='background: #0066ff; width: " . ($prediction['probability'] * 100) . "%; height: 10px; border-radius: 5px;'></div>
                        </div>
                        <small>احتمالية: " . ($prediction['probability'] * 100) . "% - متوقع في: {$prediction['predicted_for']}</small>
                      </div>";
            }
            ?>
        </div>
    </div>
    
    <script>
        function scanSystem() {
            fetch('ai_scan.php')
                .then(response => response.text())
                .then(data => {
                    alert('✅ تم مسح النظام:\n' + data);
                    location.reload();
                });
        }
        
        function trainAI() {
            fetch('ai_train.php')
                .then(response => response.text())
                .then(data => {
                    alert('🧠 تم تدريب الذكاء الاصطناعي:\n' + data);
                });
        }
        
        function generateReport() {
            window.open('ai_report.php', '_blank');
        }
        
        function clearAlerts() {
            if (confirm('هل تريد مسح جميع الإنذارات؟')) {
                fetch('ai_clear_alerts.php')
                    .then(() => {
                        alert('🗑️ تم مسح الإنذارات');
                        location.reload();
                    });
            }
        }
        
        // تحديث تلقائي كل 30 ثانية
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
