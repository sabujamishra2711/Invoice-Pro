<?php
// Validation Helper Functions

class Validator
{
    private $errors = [];

    public function validate($data, $rules)
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    private function applyRule($field, $value, $rule)
    {
        switch ($rule) {
            case 'required':
                if ($value === null || $value === '') {
                    $this->errors[$field][] = "$field is required";
                }
                break;

            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = "$field must be a valid email";
                }
                break;

            case 'numeric':
                if ($value && !is_numeric($value)) {
                    $this->errors[$field][] = "$field must be numeric";
                }
                break;

            case 'positive':
                if ($value && $value <= 0) {
                    $this->errors[$field][] = "$field must be positive";
                }
                break;

            case 'date':
                if ($value && !strtotime($value)) {
                    $this->errors[$field][] = "$field must be a valid date";
                }
                break;
        }
    }

    // Supported currencies (ISO 4217)
    public static function validCurrencies(): array
    {
        return [
            'INR','USD','EUR','GBP','AED','SGD','AUD','CAD',
            'JPY','CNY','HKD','MYR','IDR','PHP','THB','KRW','NZD','BDT','LKR','NPR','PKR',
            'CHF','SEK','NOK','DKK','PLN','CZK','HUF','RON','TRY','RUB',
            'BRL','MXN','CLP','COP','ARS',
            'SAR','QAR','KWD','BHD','OMR','ZAR','EGP','NGN',
        ];
    }

    // Specific validation methods
    public static function validateInvoiceData($data)
    {
        $validator = new self();
        $rules = [
            'client_id' => ['required', 'numeric'],
            'items' => ['required'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date']
        ];

        if (!$validator->validate($data, $rules)) {
            return $validator->getErrors();
        }

        // Validate currency if provided
        if (!empty($data['currency']) && !in_array($data['currency'], self::validCurrencies(), true)) {
            $validator->errors['currency'][] = "Invalid currency code";
        }

        // Additional business logic validation
        if (strtotime($data['due_date']) < strtotime($data['issue_date'])) {
            $validator->errors['due_date'][] = "Due date must be after issue date";
        }

        // Validate items
        if (!empty($data['items'])) {
            foreach ($data['items'] as $index => $item) {
                if (empty($item['description'])) {
                    $validator->errors["items.$index.description"][] = "Item description is required";
                }
                if (!isset($item['quantity']) || $item['quantity'] <= 0) {
                    $validator->errors["items.$index.quantity"][] = "Item quantity must be positive";
                }
                if (!isset($item['rate']) || $item['rate'] < 0) {
                    $validator->errors["items.$index.rate"][] = "Item rate must be non-negative";
                }
            }
        }

        return empty($validator->errors) ? true : $validator->errors;
    }

    public static function validatePaymentData($data)
    {
        $validator = new self();
        $rules = [
            'invoice_id' => ['required', 'numeric'],
            'amount' => ['required', 'numeric', 'positive'],
            'payment_date' => ['required', 'date'],
            'method' => ['required']
        ];

        if (!$validator->validate($data, $rules)) {
            return $validator->getErrors();
        }

        return true;
    }

    public static function validateClientData($data)
    {
        $validator = new self();
        $rules = [
            'name' => ['required']
        ];

        if (isset($data['email'])) {
            $rules['email'] = ['email'];
        }

        if (!$validator->validate($data, $rules)) {
            return $validator->getErrors();
        }

        return true;
    }
}
