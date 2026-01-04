<?php
// ملف: ai_core.php
class AICore {
    private $pdo;
    private $settings;
    private $alerts = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }
    
    private function loadSettings() {
        $stmt = $this->pdo->query("SELECT * FROM ai_settings WHERE id = 1");
        $this->settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$this->settings) {
            $this->settings = [
                'api_key' => '',
                'ai_model' => 'local',
                'monitoring_enabled' => 1,
                'alert_threshold' => 7,
                'learning_enabled' => 1
            ];
        }
    }
    
    // ===== مراقبة النشاط =====
    public function monitorActivity($user_id, $activity_type, $data) {
        if (!$this->settings['monitoring_enabled']) {
            return null;
        }
        
        // تسجيل النشاط
        $log_id = $this->logActivity($user_id, $activity_type, $data);
        
        // التحليل الفوري
        $analysis = $this->analyzeActivity($activity_type, $data);
        
        // إذا كان مستوى الخطورة عالي
        if ($analysis['risk_level'] >= $this->settings['alert_threshold']) {
            $this->createAlert($activity_type, $analysis['message'], $analysis['risk_level']);
        }
        
        // التعلم التلقائي
        if ($this->settings['learning_enabled']) {
            $this->learnFromActivity($activity_type, $data, $analysis);
        }
        
        return $analysis;
    }
    
    private function logActivity($user_id, $type, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO ai_activity_logs 
            (user_id, activity_type, activity_data, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $type,
            json_encode($data, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    // ===== تحليل النشاط (محلي + ذكاء اصطناعي) =====
    private function analyzeActivity($type, $data) {
        // التحليل المحلي الأولي
        $local_analysis = $this->localAnalysis($type, $data);
        
        // إذا كان النموذج "local" فقط
        if ($this->settings['ai_model'] === 'local') {
            return $local_analysis;
        }
        
        // إذا كان هناك مفتاح API، استخدم الذكاء الاصطناعي الخارجي
        if (!empty($this->settings['api_key'])) {
            $ai_analysis = $this->callExternalAI($type, $data);
            
            // دمج التحليلين
            return $this->mergeAnalyses($local_analysis, $ai_analysis);
        }
        
        return $local_analysis;
    }
    
    private function localAnalysis($type, $data) {
        $risk_level = 0;
        $message = "نشاط عادي";
        
        // قواعد اكتشاف محلية
        switch($type) {
            case 'login_failed':
                $risk_level = 3;
                $message = "تسجيل دخول فاشل";
                break;
                
            case 'file_upload':
                $file_size = $data['size'] ?? 0;
                if ($file_size > 50000000) { // 50MB
                    $risk_level = 4;
                    $message = "تحميل ملف كبير الحجم";
                } elseif (preg_match('/\.(exe|bat|sh)$/i', $data['name'] ?? '')) {
                    $risk_level = 6;
                    $message = "تحميل ملف قابل للتنفيذ";
                }
                break;
                
            case 'multiple_logins':
                $risk_level = 5;
                $message = "تسجيلات دخول متعددة من أماكن مختلفة";
                break;
                
            case 'unusual_time':
                $hour = date('H');
                if ($hour > 1 && $hour < 5) {
                    $risk_level = 4;
                    $message = "نشاط في وقت متأخر من الليل";
                }
                break;
                
            case 'data_access':
                $risk_level = 2;
                $message = "وصول إلى بيانات حساسة";
                break;
        }
        
        // تحقق من النشاط المتكرر
        if ($this->isRepeatedActivity($type, $data)) {
            $risk_level += 2;
            $message .= " (متكرر)";
        }
        
        return [
            'risk_level' => min($risk_level, 10),
            'message' => $message,
            'source' => 'local_ai',
            'confidence' => 0.7
        ];
    }
    
    private function callExternalAI($type, $data) {
        // استخدام Gemini API (مجاني)
        if (empty($this->settings['api_key'])) {
            return ['risk_level' => 0, 'message' => 'لا يوجد مفتاح API', 'source' => 'none'];
        }
        
        $prompt = "تحليل نشاط أمني في نظام مراقبة:
        نوع النشاط: {$type}
        البيانات: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "
        
        قدم تحليلاً أمنياً عربياً مع:
        1. مستوى الخطورة من 1 إلى 10
        2. وصف مختصر
        3. نصيحة أمنية";
        
        $analysis = $this->callGeminiAPI($prompt);
        
        // استخراج مستوى الخطورة من النص
        preg_match('/مستوى الخطورة.*?(\d+)/', $analysis, $matches);
        $risk_level = isset($matches[1]) ? intval($matches[1]) : 0;
        
        return [
            'risk_level' => $risk_level,
            'message' => $analysis,
            'source' => 'gemini_ai',
            'confidence' => 0.85
        ];
    }
    
    private function callGeminiAPI($prompt) {
        $api_key = $this->settings['api_key'];
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$api_key}";
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if(isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }
        
        return "لا يمكن تحليل النشاط حالياً";
    }
    
    private function mergeAnalyses($local, $ai) {
        // متوسط مستوى الخطورة
        $risk_level = ($local['risk_level'] + $ai['risk_level']) / 2;
        
        // الثقة الأعلى
        $confidence = max($local['confidence'], $ai['confidence']);
        
        return [
            'risk_level' => $risk_level,
            'message' => $ai['message'] . "\n\n" . $local['message'],
            'source' => 'hybrid_ai',
            'confidence' => $confidence
        ];
    }
    
    // ===== إنشاء إنذارات =====
    private function createAlert($type, $message, $risk_level) {
        $severity = 'low';
        if ($risk_level >= 8) $severity = 'critical';
        elseif ($risk_level >= 6) $severity = 'high';
        elseif ($risk_level >= 4) $severity = 'medium';
        
        $stmt = $this->pdo->prepare("
            INSERT INTO ai_alerts (alert_type, alert_message, severity) 
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([$type, $message, $severity]);
        
        $this->alerts[] = [
            'type' => $type,
            'message' => $message,
            'severity' => $severity,
            'time' => date('Y-m-d H:i:s')
        ];
        
        // إرسال تنبيه فوري
        $this->sendImmediateAlert($type, $message, $severity);
    }
    
    private function sendImmediateAlert($type, $message, $severity) {
        // يمكن إضافة: إشعارات بريد، رسائل، إلخ.
        error_log("🚨 ALERT [{$severity}]: {$type} - {$message}");
        
        // تخزين في الجلسة للعرض الفوري
        $_SESSION['ai_alerts'][] = [
            'type' => $type,
            'message' => $message,
            'severity' => $severity,
            'time' => time()
        ];
    }
    
    // ===== التعلم التلقائي =====
    private function learnFromActivity($type, $data, $analysis) {
        // حفظ التحليل للتعلم المستقبلي
        $stmt = $this->pdo->prepare("
            INSERT INTO ai_analyses (analysis_type, analysis_data, confidence_score, is_alert) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $type,
            json_encode($analysis, JSON_UNESCAPED_UNICODE),
            $analysis['confidence'],
            ($analysis['risk_level'] >= $this->settings['alert_threshold']) ? 1 : 0
        ]);
        
        // تحديث أنماط النشاط
        $this->updateActivityPatterns($type, $data, $analysis);
    }
    
    private function updateActivityPatterns($type, $data, $analysis) {
        // هنا يمكن إضافة منطق التعلم الآلي
        // مثل: كشف الأنماط، تصنيف النشاط، إلخ.
    }
    
    // ===== وظائف مساعدة =====
    private function isRepeatedActivity($type, $data) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM ai_activity_logs 
            WHERE activity_type = ? 
            AND created_at > datetime('now', '-5 minutes')
        ");
        
        $stmt->execute([$type]);
        $result = $stmt->fetch();
        
        return $result['count'] > 3;
    }
    
    // ===== واجهات عامة =====
    public function getRecentAlerts($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM ai_alerts 
            WHERE resolved = 0 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getActivitySummary($hours = 24) {
        $stmt = $this->pdo->prepare("
            SELECT 
                activity_type,
                COUNT(*) as count,
                AVG(risk_level) as avg_risk
            FROM ai_activity_logs 
            WHERE created_at > datetime('now', ?)
            GROUP BY activity_type
            ORDER BY count DESC
        ");
        
        $stmt->execute(["-{$hours} hours"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getRiskLevel() {
        $stmt = $this->pdo->query("
            SELECT AVG(risk_level) as avg_risk 
            FROM ai_activity_logs 
            WHERE created_at > datetime('now', '-1 hour')
        ");
        
        $result = $stmt->fetch();
        return $result['avg_risk'] ?? 0;
    }
    
    public function getActiveAlerts() {
        return $this->alerts;
    }
}
?>
