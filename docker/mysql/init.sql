-- Initialize Episciences database
-- This script runs when the MySQL container starts for the first time

-- Create test database for PHPUnit tests
CREATE DATABASE IF NOT EXISTS `episciences_test`;
CREATE DATABASE IF NOT EXISTS `episciences_auth`;

-- Grant permissions
GRANT ALL PRIVILEGES ON `episciences`.* TO 'episciences'@'%';
GRANT ALL PRIVILEGES ON `episciences_test`.* TO 'episciences'@'%';
GRANT ALL PRIVILEGES ON `episciences_auth`.* TO 'episciences'@'%';

-- Grant permissions to root as well
GRANT ALL PRIVILEGES ON `episciences_test`.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON `episciences_auth`.* TO 'root'@'%';

FLUSH PRIVILEGES;