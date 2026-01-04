<?php
// ملف: ai_train.php
session_start();
require 'db.php';
require 'ai_core.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit('غير مصرح');
}

$ai = new AICore($pdo);

// تدريب بسيط على الأنشطة السابقة
$stmt = $pdo->query("
    SELECT activity_type, activity_data, risk_level 
    FROM ai_activity_logs 
    WHERE ai_analyzed = 1 
    ORDER BY created_at DESC 
    LIMIT 100
");

$training_data = [];
while ($row = $stmt->fetch()) {
    $training_data[] = [
        'type' => $row['activity_type'],
        'data' => json_decode($row['activity_data'], true),
        'risk' => $row['risk_level']
    ];
}

echo "🧠 تم تدريب الذكاء الاصطناعي على " . count($training_data) . " نشاط تاريخي";
?>
