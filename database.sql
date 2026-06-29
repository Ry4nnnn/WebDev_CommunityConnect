CREATE DATABASE IF NOT EXISTS CommunityConnect;
USE CommunityConnect;

CREATE TABLE IF NOT EXISTS Users (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    FullName VARCHAR(100) NOT NULL,
    Email VARCHAR(100) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    Role ENUM('Admin', 'Resident') NOT NULL DEFAULT 'Resident',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS CommunityServices (
    ServiceID INT AUTO_INCREMENT PRIMARY KEY,
    Title VARCHAR(150) NOT NULL,
    Description TEXT NOT NULL,
    EventDate DATE NOT NULL,
    Location VARCHAR(150) NOT NULL,
    Capacity INT NOT NULL,
    AdminID INT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (AdminID) REFERENCES Users(UserID)
);

CREATE TABLE IF NOT EXISTS ParticipationRequests (
    RequestID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    ServiceID INT NOT NULL,
    Status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    RequestDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES Users(UserID),
    FOREIGN KEY (ServiceID) REFERENCES CommunityServices(ServiceID),
    UNIQUE (UserID, ServiceID)
);

CREATE TABLE IF NOT EXISTS Feedback (
    FeedbackID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    ServiceID INT NOT NULL,
    Rating INT NOT NULL,
    Comment TEXT NOT NULL,
    SubmittedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES Users(UserID),
    FOREIGN KEY (ServiceID) REFERENCES CommunityServices(ServiceID)
);

INSERT IGNORE INTO Users (UserID, FullName, Email, PasswordHash, Role)
VALUES 
(1, 'Admin User', 'admin@communityconnect.com', 'admin123', 'Admin');

INSERT IGNORE INTO CommunityServices (ServiceID, Title, Description, EventDate, Location, Capacity, AdminID)
VALUES
(1, 'Neighbourhood Clean-Up Campaign', 'Join us to clean the neighbourhood and promote a cleaner community.', '2026-07-05', 'Klang Valley Park', 30, 1),
(2, 'Charity Donation Drive', 'Help collect and organize donation items for families in need.', '2026-07-10', 'Community Hall', 25, 1),
(3, 'Tree Planting Activity', 'Support SDG 11 by planting trees and improving green spaces.', '2026-07-15', 'Taman Community Area', 40, 1),
(4, 'Educational Workshop', 'A workshop to educate residents about sustainable living.', '2026-07-20', 'Harmony Learning Centre', 20, 1);