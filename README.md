# Payout Service API

## Overview
The Payout Service API allows clients to create payouts for sellers. This project is built on Laravel 8 and follows a layered tier architecture. The project handles requests and provides payouts on the basis of `base_currency` on which the client was onboarded to provide a uniform remittance.

## Assignment Context
The goal of this project was to expose an API endpoint that accepts a list of sold items and creates payouts for sellers, while considering specific requirements:
- **Single Seller Payouts**: Each payout is for a single seller and uses a single currency.
- **Minimize Transactions**: Minimize the number of transactions to reduce costs.
- **Payout Limits**: Split payouts if they exceed a predefined amount.
- **Item Linkage**: Each payout must be linked with at least one item to maintain traceability.

The project follows best OOP practices and includes tests to validate various scenarios.

## Features
- **Payout Creation**: Create payouts for sellers by submitting items with price and currency details.
- **Validation**: Comprehensive validation ensures data integrity.
- **Event Dispatching**: Payout creation events are dispatched to enable any additional business logic (e.g., notifications).
- **Service Layer**: Follows the Service Layer design pattern for better separation of concerns.

### POST `/api/payouts`
Create payouts for sellers by providing an array of sold items.

**Request Format**:
```json
{
  "sold_items": [
    {
      "seller_reference": 1,
      "channel_item_code": "Test_W739937"
    },
    {
      "seller_reference": 1,
      "channel_item_code": "Test_W739936"
    },
    {
      "seller_reference": 1,
      "channel_item_code": "Test_W7394"
    }
  ]
}
```

**Response Format**:
- **Success (201 Created)**:
  ```json
  {
  "payouts": {
    "Johnston-Funk": {
      "U.S.A Payouts": [
        {
          "payout_id": 3,
          "seller_reference": 1,
          "original_amount": 985,
          "converted_amount": 985,
          "original_currency": "USD",
          "converted_currency": "USD",
          "items": [
            {
              "Item ID": 21,
              "Item Channel Code": "Test_W7394",
              "Item Name": "Test T4",
              "Item Amount": 600,
              "Item Unit Amount": 300,
              "Item Currency": "USD",
              "Item Quantity": 2
            },
            {
              "Item ID": 23,
              "Item Channel Code": "Test_W739937",
              "Item Name": "Test T7",
              "Item Amount": 385,
              "Item Unit Amount": 55,
              "Item Currency": "USD",
              "Item Quantity": 7
            }
          ]
        },
        {
          "payout_id": 3,
          "seller_reference": 1,
          "original_amount": 970,
          "converted_amount": 970,
          "original_currency": "USD",
          "converted_currency": "USD",
          "items": [
            {
              "Item ID": 22,
              "Item Channel Code": "Test_W739936",
              "Item Name": "Test T6",
              "Item Amount": 915,
              "Item Unit Amount": 915,
              "Item Currency": "USD",
              "Item Quantity": 1
            },
            {
              "Item ID": 23,
              "Item Channel Code": "Test_W739937",
              "Item Name": "Test T7",
              "Item Amount": 55,
              "Item Unit Amount": 55,
              "Item Currency": "USD",
              "Item Quantity": 1
            }
          ]
        },
        {
          "payout_id": 3,
          "seller_reference": 1,
          "original_amount": 165,
          "converted_amount": 165,
          "original_currency": "USD",
          "converted_currency": "USD",
          "items": [
            {
              "Item ID": 23,
              "Item Channel Code": "Test_W739937",
              "Item Name": "Test T7",
              "Item Amount": 165,
              "Item Unit Amount": 55,
              "Item Currency": "USD",
              "Item Quantity": 3
            }
          ]
          }
        ]  
      }
    }
  }

  ```
- **Validation Error (422 Unprocessable Entity)**:
  ```json
  {
  "errors": {
    "sold_items": [
      "The sold_items field is required."
    ]
  }
  }

  ```

**Sample cURL Request**:
```bash
    curl --location 'http://vest.test/api/payouts' \
      --header 'Content-Type: application/json' \
      --data '{
          "sold_items": [
              {
                  "seller_reference": 1,
                  "channel_item_code": "Test_W739937"
              },
              {
                  "seller_reference": 1,
                  "channel_item_code": "Test_W739936"
              },
              {
                  "seller_reference": 1,
                  "channel_item_code": "Test_W7394"
              }
          ]
      }'
```

## Validation Rules
- `sold_items`: Required, must be an array.
- `sold_items.*.seller_reference`: Required, must be an integer and exist in the sellers table.
- `sold_items.*.channel_item_code`: Required, must be a string with a maximum length of 255 characters.

## Installation Instructions
1. **Clone the Repository**: Clone the project from your version control system.
   ```bash
   git clone <repository-url>
   ```
2. **Install Dependencies**:
   - Run `composer install` to install PHP dependencies.
3. **Environment Configuration**:
   - Copy `.env.example` to `.env` and update your environment configurations.
   - Set up your database details, mail server details, etc.
   - **Note**: The environment keys have already been added in the `.env` file for convenience.
4. **Generate Application Key**:
   ```bash
   php artisan key:generate
   ```
5. **Run Migrations**:
   ```bash
   php artisan migrate
   ```
6. **Run Seeders** to populate seller data, run the following command:
   ```bash
   php artisan db:seed
   ```
7. **Fill Data Using Factories** (Optional): You can use Laravel factories to generate sample data:
   ```bash
   php artisan tinker
   ```
   Inside the tinker shell, use the model factories to create data, e.g., `Item::factory()->count(10)->create();`
8. **Install Laravel Valet** (Optional): To serve the application using Laravel Valet, install Valet with the following command:
   ```bash
   composer global require laravel/valet
   ```
   Then run:
   ```bash
   valet install
   ```
9. **Create Valet Link**: Navigate to the project directory and run:
   ```bash
   valet link vest
   ```
   - This will create a local domain (e.g., `http://vest.test`) that can be used to access the application.
   - **Note**: Update the `.env` file to include the Valet link for `APP_URL`:
     ```
     APP_URL=http://vest.test
     ```

## Running the Project
- **API Testing**: You can use tools like Postman to test the `/api/payouts` endpoint.

## Requirements
- **PHP**: Version 7.4 or higher.
- **Composer**: For managing PHP dependencies.
- **Node.js and NPM**: For managing JavaScript dependencies.
- **MySQL**: Or any supported relational database for storing application data.
- **Laravel Valet** (Optional): For serving the application locally.

## Testing
The project includes feature tests for various scenarios, including:
- Creating payouts for single or multiple sellers.
- Validating incorrect input (e.g., unsupported currency, negative price).
- Testing concurrency with simultaneous requests.

To run the tests, use the following command:
```bash
php artisan test
```

## Future Improvements
If I had more time to work on this project, I would have included the following features and improvements:

1. **Enhanced Error Handling**: Improved error handling mechanisms for edge cases, such as network failures or database inconsistencies.
2. **Added Docker Setup**: Added Docker to run this project in isolation using images so that users don't have to install all dependent libraries explicitly.
3. **Rate Limiting**: Implemented rate limiting to prevent misuse of the API and enhance security.
4. **Unit and Integration Tests**: Would have added more functional, feature, and unit test cases.
5. **Currency Conversion Service**: Introduced a currency conversion service to support automatic payouts in multiple currencies.

## Project Structure
- **`app/`**: Contains core application code, including Providers, Models, HTTP controllers, Repositories, Events, and Services.
- **`routes/`**: Defines the routes for the application ( `api.php`).
- **`config/`**: Configuration files for various services (`database.php`, `mail.php`, `auth.php`, etc.).

## Dependencies
- **Composer Dependencies**: Managed in `composer.json`. Includes Laravel framework and other PHP packages.

## Usage Notes
- The service is designed with a focus on modularity and scalability.
- Make sure to configure the environment correctly before using the API.

## License
This project is licensed under the MIT License. See the LICENSE file for details.

## Contributing
Contributions are welcome! Please submit a pull request or open an issue to discuss any changes.

## Contact
For any inquiries, please contact [chiranshu.arora011@gmail.com].
# vestiaire_collection
# vestiaire_collection
