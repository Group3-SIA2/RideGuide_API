# RideGuide API

A REST API built with Laravel that provides data and services for the RideGuide mobile app.

## Overview

RideGuide API is a comprehensive transportation management system designed specifically for General Santos City. It handles route planning, fare calculation, and provides essential transportation information to support seamless mobility services.

## Features

- Route planning and optimization
- Fare calculation system
- Transportation information management
- RESTful API endpoints
- Mobile app integration

## Technology Stack

- PHP (39.1%)
- Blade Templates (60.3%)
- Laravel Framework
- MySQL/Database

## Prerequisites

- PHP 8.0 or higher
- Composer
- Laravel 9.0 or higher
- MySQL 5.7 or higher
- Git

## Installation

1. Clone the repository
   git clone https://github.com/Group3-SIA2/RideGuide_API.git
   cd RideGuide_API

2. Install dependencies
   composer install

3. Create environment configuration
   cp .env.example .env

4. Generate application key
   php artisan key:generate

5. Configure database in .env file
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=rideguide
   DB_USERNAME=root
   DB_PASSWORD=

6. Run migrations
   php artisan migrate

7. Start the development server
   php artisan serve

## API Documentation

The API provides endpoints for:

- Routes and route planning
- Fare calculations
- Transportation schedules
- Location information
- User management

## Usage

Access the API at http://localhost:8000/api

Example endpoints:
- GET /api/routes
- POST /api/fares/calculate
- GET /api/locations
- GET /api/schedules

## Contributing

Contributions are welcome. Please follow the existing code style and create a pull request with your changes.

## License

This project is licensed under the MIT License.

## Contact

For issues or inquiries, please open an issue on the GitHub repository.
