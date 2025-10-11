# 日期過濾修復報告

## 問題描述
當使用 start_date 和 end_date 進行日期範圍過濾時，分析表顯示為空。

## 根本原因分析
1. **錯誤的日期欄位使用**: 
   - Appointments 表應該使用 `appointment_date` 而不是 `created_at`
   - Medical Records 表應該同時考慮 `visit_date` 和 `created_at`

2. **JOIN 條件錯誤**: 
   - Doctor revenue 查詢中的 JOIN 條件將日期過濾放在了錯誤的位置
   - 導致 LEFT JOIN 變成了 INNER JOIN，過濾掉了沒有數據的醫生

3. **SQL 語法問題**: 
   - 某些查詢中出現重複的 ORDER BY 子句

## 修復內容

### 1. Appointments 查詢修復
**之前:**
```php
$appointments_query = "SELECT COUNT(*) as count FROM appointments WHERE " . $date_filter;
```

**之後:**
```php
$appointments_date_filter = str_replace('created_at', 'appointment_date', $date_filter);
$appointments_query = "SELECT COUNT(*) as count FROM appointments WHERE " . $appointments_date_filter;
```

### 2. Service Performance 查詢修復
**之前:**
```php
WHERE " . str_replace('created_at', 'mr.created_at', $date_filter) . "
```

**之後:**
```php
WHERE 1=1
// 然後添加正確的日期條件，同時考慮 visit_date 和 created_at
```

### 3. Doctor Revenue 查詢完全重寫
**之前:** 在 JOIN 條件中添加日期過濾
**之後:** 將日期過濾移到 WHERE 子句中，並正確處理多個表的日期欄位

### 4. Appointment Status 查詢修復
使用 `appointment_date` 而不是 `created_at`

## 測試建議
1. 使用提供的測試腳本 `test_date_filter.php` 驗證修復
2. 在報表頁面測試不同的日期範圍
3. 確保所有分析表都能正確顯示數據

## 影響的功能
- 月度收入趨勢圖
- 服務績效分析
- 醫生收入分析  
- 付款方式分析
- 頂級患者分析
- 預約狀態分析

所有這些功能現在都應該能正確處理日期範圍過濾。