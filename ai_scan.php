<?php
// ملف: ai_scan.php
session_start();
require 'db.php';
require 'ai_core.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit('غير مصرح');
}

$ai = new AICore($pdo);

// مسح النظام
$scan_results = [];

// 1. تحليل النشاط
$activities = $ai->getActivitySummary(24);
$scan_results[] = "📊 تم تحليل " . count($activities) . " نوع نشاط";

// 2. اكتشاف الأنماط غير الطبيعية
$stmt = $pdo->query("
    SELECT activity_type, COUNT(*) as count 
    FROM ai_activity_logs 
    WHERE created_at > datetime('now', '-1 hour') 
    GROUP BY activity_type 
    HAVING count > 10
");

while ($pattern = $stmt->fetch()) {
    $scan_results[] = "⚠️ نشاط متكرر: {$pattern['activity_type']} ({$pattern['count']} مرة)";
}

// 3. تحليل الأخطاء
$error_log = __DIR__ . '/logs/error.log';
if (file_exists($error_log)) {
    $errors = file($error_log, FILE_IGNORE_NEW_LINES);
    $error_count = count($errors);
    if ($error_count > 0) {
        $scan_results[] = "❌ تم اكتشاف {$error_count} خطأ في السجلات";
    }
}

// 4. التوصيات
$risk_level = $ai->getRiskLevel();
if ($risk_level > 5) {
    $scan_results[] = "🚨 توصية: مستوى الخطورة مرتفع ({$risk_level}/10)";
    $scan_results[] = "💡 اقتراح: تفعيل الحماية الإضافية";
}

echo implode("\n", $scan_results);
?>
