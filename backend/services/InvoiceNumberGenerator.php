<?php
// Invoice Number Generation Service

class InvoiceNumberGenerator
{
    private $db;
    private $userId;

    public function __construct($userId)
    {
        $this->db = getDB();
        $this->userId = $userId;
    }

    // Generate unique invoice number for user
    public function generate($prefix = 'INV')
    {
        // Get user's invoice number settings
        $settings = $this->getUserSettings();
        $userPrefix = $settings['invoice_prefix'] ?? $prefix;
        $numberFormat = $settings['number_format'] ?? 'YYYY-MM-NNNN';

        // Generate based on format
        $invoiceNumber = $this->generateFromFormat($userPrefix, $numberFormat);

        // Ensure uniqueness
        $attempts = 0;
        while ($this->numberExists($invoiceNumber) && $attempts < 10) {
            $invoiceNumber = $this->generateFromFormat($userPrefix, $numberFormat, $attempts + 1);
            $attempts++;
        }

        if ($attempts >= 10) {
            throw new Exception('Unable to generate unique invoice number after 10 attempts');
        }

        return $invoiceNumber;
    }

    private function getUserSettings()
    {
        $stmt = $this->db->prepare("SELECT * FROM settings WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        return $stmt->fetch() ?: [];
    }

    private function generateFromFormat($prefix, $format, $attempt = 0)
    {
        $year = date('Y');
        $month = date('m');
        $day = date('d');

        // Replace format placeholders
        $number = $format;
        $number = str_replace('YYYY', $year, $number);
        $number = str_replace('YY', substr($year, -2), $number);
        $number = str_replace('MM', $month, $number);
        $number = str_replace('DD', $day, $number);

        // Generate sequence number
        if (strpos($number, 'NNNN') !== false) {
            $sequence = $this->getNextSequence($prefix . $year . $month);
            $sequence = str_pad($sequence + $attempt, 4, '0', STR_PAD_LEFT);
            $number = str_replace('NNNN', $sequence, $number);
        } elseif (strpos($number, 'NNN') !== false) {
            $sequence = $this->getNextSequence($prefix . $year . $month);
            $sequence = str_pad($sequence + $attempt, 3, '0', STR_PAD_LEFT);
            $number = str_replace('NNN', $sequence, $number);
        } elseif (strpos($number, 'NN') !== false) {
            $sequence = $this->getNextSequence($prefix . $year . $month);
            $sequence = str_pad($sequence + $attempt, 2, '0', STR_PAD_LEFT);
            $number = str_replace('NN', $sequence, $number);
        }

        return $prefix . '-' . $number;
    }

    private function getNextSequence($prefix)
    {
        $stmt = $this->db->prepare("
            SELECT invoice_number 
            FROM invoices 
            WHERE user_id = ? AND invoice_number LIKE ? 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $searchPattern = $prefix . '%';
        $stmt->execute([$this->userId, $searchPattern]);
        $lastInvoice = $stmt->fetch();

        if ($lastInvoice) {
            // Extract number from last invoice
            $lastNumber = $lastInvoice['invoice_number'];
            if (preg_match('/-(\d+)$/', $lastNumber, $matches)) {
                return (int)$matches[1];
            }
        }

        return 0;
    }

    private function numberExists($invoiceNumber)
    {
        $stmt = $this->db->prepare("
            SELECT id FROM invoices 
            WHERE user_id = ? AND invoice_number = ?
        ");
        $stmt->execute([$this->userId, $invoiceNumber]);
        return $stmt->fetch() !== false;
    }

    // Validate invoice number format
    public static function validateFormat($format)
    {
        $validPlaceholders = ['YYYY', 'YY', 'MM', 'DD', 'NNNN', 'NNN', 'NN'];
        $placeholderPattern = '/(' . implode('|', $validPlaceholders) . ')/';

        // Check if format contains at least one number placeholder
        if (!preg_match('/N+/', $format)) {
            return false;
        }

        // Check if all placeholders are valid
        $parts = preg_split($placeholderPattern, $format, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        foreach ($parts as $part) {
            if (preg_match($placeholderPattern, $part) && !in_array($part, $validPlaceholders)) {
                return false;
            }
        }

        return true;
    }

    // Get default formats
    public static function getDefaultFormats()
    {
        return [
            'YYYY-MM-NNNN' => '2024-01-0001',
            'YY-MM-NNNN' => '24-01-0001',
            'INV-YYYY-NNNN' => 'INV-2024-0001',
            'NNNN' => '0001'
        ];
    }
}
