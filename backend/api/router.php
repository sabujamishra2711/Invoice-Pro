<?php
// Route definitions and request routing

function routeRequest($method, $route, $input)
{
    // Route mapping
    $routes = [
        // Auth routes
        'auth.login'            => ['POST', 'AuthController@login'],
        'auth.register'         => ['POST', 'AuthController@register'],
        'auth.google'           => ['POST', 'AuthController@googleLogin'],
        'auth.otp.send'         => ['POST', 'AuthController@sendOtp'],
        'auth.otp.verify'       => ['POST', 'AuthController@verifyOtp'],
        'auth.reset'            => ['POST', 'AuthController@resetPassword'],
        'auth.password.change'  => ['POST', 'AuthController@changePassword'],
        'auth.profile.update'   => ['POST', 'AuthController@updateProfile'],

        // Client routes
        'client.list' => ['GET', 'ClientController@list'],
        'client.create' => ['POST', 'ClientController@create'],
        'client.get' => ['GET', 'ClientController@get'],
        'client.update' => ['PUT', 'ClientController@update'],
        'client.delete' => ['DELETE', 'ClientController@delete'],

        // Invoice routes
        'invoice.list' => ['GET', 'InvoiceController@list'],
        'invoice.create' => ['POST', 'InvoiceController@create'],
        'invoice.get' => ['GET', 'InvoiceController@get'],
        'invoice.update' => ['PUT', 'InvoiceUpdateController@update'],
        'invoice.delete' => ['DELETE', 'InvoiceUpdateController@delete'],

        // Payment routes
        'payment.create' => ['POST', 'PaymentController@create'],
        'payment.list' => ['GET', 'PaymentController@list'],
        'payment.export' => ['GET', 'PaymentController@exportCsv'],

        // PDF routes
        'pdf.generate' => ['GET', 'PdfController@generatePdf'],
        'pdf.download' => ['GET', 'PdfController@downloadPdf'],
        'invoice.pdf' => ['GET', 'PdfController@downloadPdf'],
        'invoice.duplicate' => ['POST', 'InvoiceController@duplicate'],
        'invoice.export' => ['GET', 'InvoiceController@exportCsv'],

        // Dashboard routes
        'dashboard.get' => ['GET', 'DashboardController@get'],
        'dashboard.stats' => ['GET', 'DashboardController@getStats'],

        // Settings routes
        'settings.get' => ['GET', 'SettingsController@get'],
        'settings.update' => ['POST', 'SettingsController@update'],

        // Email settings routes
        'email.settings.get' => ['GET', 'SettingsController@getEmailSettings'],
        'email.settings.update' => ['POST', 'SettingsController@updateEmailSettings'],

        // Email send routes
        'invoice.email.send' => ['POST', 'EmailController@send'],
        'email.settings.test' => ['POST', 'EmailController@testConnection'],

        // Version / Plan routes
        'version.limits'   => ['GET',  'VersionController@getLimits'],
        'version.plan.set' => ['POST', 'VersionController@setPlan'],

        // Razorpay routes
        'razorpay.order.create'  => ['POST', 'RazorpayController@createOrder'],
        'razorpay.payment.verify'=> ['POST', 'RazorpayController@verifyPayment'],
        'razorpay.pricing'       => ['GET',  'RazorpayController@pricing'],
    ];

    // Check if route exists
    if (!isset($routes[$route])) {
        return [
            'success' => false,
            'error_code' => 'ROUTE_NOT_FOUND',
            'message' => 'Route not found',
            'http_code' => 404
        ];
    }

    // Check method
    $routeConfig = $routes[$route];
    if ($method !== $routeConfig[0]) {
        return [
            'success' => false,
            'error_code' => 'METHOD_NOT_ALLOWED',
            'message' => 'Method not allowed',
            'http_code' => 405
        ];
    }

    // Call controller method
    $controllerMethod = $routeConfig[1];
    list($controllerName, $methodName) = explode('@', $controllerMethod);

    // Include controller
    $controllerFile = __DIR__ . '/../controllers/' . $controllerName . '.php';
    if (!file_exists($controllerFile)) {
        return [
            'success' => false,
            'error_code' => 'CONTROLLER_NOT_FOUND',
            'message' => 'Controller not found',
            'http_code' => 500
        ];
    }

    require_once $controllerFile;

    if (!class_exists($controllerName) || !method_exists($controllerName, $methodName)) {
        return [
            'success' => false,
            'error_code' => 'METHOD_NOT_FOUND',
            'message' => 'Controller method not found',
            'http_code' => 500
        ];
    }

    // Execute controller method
    $controller = new $controllerName();
    return $controller->$methodName($input);
}
