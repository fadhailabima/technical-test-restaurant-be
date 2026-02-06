<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Restaurant Backend API",
    description: "API documentation untuk Restaurant Management System dengan fitur authentication, menu, table, order management dengan status tracking, payment processing, dan comprehensive reporting."
)]
#[OA\Server(
    url: "http://localhost:8000",
    description: "Local Development Server"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
#[OA\Tag(name: "Authentication", description: "Login & Logout endpoints")]
#[OA\Tag(name: "Menus", description: "Menu management (CRUD)")]
#[OA\Tag(name: "Tables", description: "Table management (CRUD)")]
#[OA\Tag(name: "Orders", description: "Order management & workflow")]
#[OA\Tag(name: "Payments", description: "Payment processing & refunds")]
#[OA\Tag(name: "Reports", description: "Analytics & reporting")]
abstract class Controller
{
    //
}
