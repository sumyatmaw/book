CREATE TABLE `Users`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(255) NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `role` ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    `created_at` TIMESTAMP NOT NULL
);
ALTER TABLE
    `Users` ADD UNIQUE `users_email_unique`(`email`);
CREATE TABLE `Books`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `author` VARCHAR(255) NOT NULL,
    `price` DECIMAL(8, 2) NOT NULL,
    `stock` INT NOT NULL,
    `book_image` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL
);
CREATE TABLE `Orders`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `total_amount` DECIMAL(8, 2) NOT NULL,
    `status` ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL,
    `order_number` VARCHAR(255) NULL
);
CREATE TABLE `Cart_item`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `book_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(8, 2) NOT NULL,
    `totalprice` DECIMAL(8, 2) NOT NULL
);
CREATE TABLE `Categories`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(255) NOT NULL
);
CREATE TABLE `Payment`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `payment_method_id` INT NOT NULL,
    `amount` DECIMAL(8, 2) NOT NULL,
    `status` ENUM('pending','paid','rejected') NOT NULL DEFAULT 'pending',
    `payment_date` TIMESTAMP NOT NULL,
    `transaction_ref` VARCHAR(255) NOT NULL,
    `payment_slip` VARCHAR(255) NOT NULL
);


CREATE TABLE `Ratings`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `book_id` INT NOT NULL,
    `rating` INT NOT NULL,
    `comment` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL
);
CREATE TABLE `payment_method`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `method_name` VARCHAR(255) NOT NULL,
    `account_number` VARCHAR(255) NOT NULL,
    `account_holder` VARCHAR(255) NOT NULL,
    `is_active` BOOLEAN NOT NULL,
    
    `description` TEXT NOT NULL
);
CREATE TABLE `Order_item`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `book_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `price` DECIMAL(8, 2) NOT NULL
);

ALTER TABLE
    `Ratings` ADD CONSTRAINT `ratings_user_id_foreign` FOREIGN KEY(`user_id`) REFERENCES `Users`(`id`);
ALTER TABLE
    `Books` ADD CONSTRAINT `books_category_id_foreign` FOREIGN KEY(`category_id`) REFERENCES `Categories`(`id`);
ALTER TABLE
    `payment_method` ADD CONSTRAINT `payment_method_id_foreign` FOREIGN KEY(`id`) REFERENCES `Payment`(`payment_method_id`);
ALTER TABLE
    `Books` ADD CONSTRAINT `books_user_id_foreign` FOREIGN KEY(`user_id`) REFERENCES `Users`(`id`);
ALTER TABLE
    `Cart_item` ADD CONSTRAINT `cart_item_user_id_foreign` FOREIGN KEY(`user_id`) REFERENCES `Users`(`id`);
ALTER TABLE
    `Orders` ADD CONSTRAINT `orders_user_id_foreign` FOREIGN KEY(`user_id`) REFERENCES `Users`(`id`);
ALTER TABLE
    `Books` ADD CONSTRAINT `books_id_foreign` FOREIGN KEY(`id`) REFERENCES `Cart_item`(`book_id`);
ALTER TABLE
    `Ratings` ADD CONSTRAINT `ratings_book_id_foreign` FOREIGN KEY(`book_id`) REFERENCES `Books`(`id`);
ALTER TABLE
    `Order_item` ADD CONSTRAINT `order_item_order_id_foreign` FOREIGN KEY(`order_id`) REFERENCES `Orders`(`id`);
ALTER TABLE
    `Books` ADD CONSTRAINT `books_id_foreign` FOREIGN KEY(`id`) REFERENCES `Order_item`(`book_id`);
ALTER TABLE
    `Payment` ADD CONSTRAINT `payment_order_id_foreign` FOREIGN KEY(`order_id`) REFERENCES `Orders`(`id`);