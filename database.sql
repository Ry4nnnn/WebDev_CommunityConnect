DROP DATABASE IF EXISTS CommunityConnect;
CREATE DATABASE CommunityConnect;
USE CommunityConnect;

CREATE TABLE Users (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    FullName VARCHAR(100) NOT NULL,
    Email VARCHAR(100) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    Role ENUM('Admin', 'Resident') NOT NULL DEFAULT 'Resident',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE CommunityServices (
    ServiceID INT AUTO_INCREMENT PRIMARY KEY,
    Title VARCHAR(150) NOT NULL,
    Description TEXT NOT NULL,
    EventDate DATE NOT NULL,
    EventStartTime TIME NOT NULL,
    EventEndTime TIME NOT NULL,
    Location VARCHAR(150) NOT NULL,
    Capacity INT NOT NULL,
    AdminID INT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (AdminID) REFERENCES Users(UserID)
);

CREATE TABLE ParticipationRequests (
    RequestID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    ServiceID INT NOT NULL,
    Status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    RequestDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES Users(UserID),
    FOREIGN KEY (ServiceID) REFERENCES CommunityServices(ServiceID) ON DELETE CASCADE,
    UNIQUE (UserID, ServiceID)
);

CREATE TABLE Feedback (
    FeedbackID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    ServiceID INT NOT NULL,
    Rating INT NOT NULL,
    Comment TEXT NOT NULL,
    SubmittedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES Users(UserID),
    FOREIGN KEY (ServiceID) REFERENCES CommunityServices(ServiceID) ON DELETE CASCADE,
    UNIQUE (UserID, ServiceID)
);

INSERT INTO Users (UserID, FullName, Email, PasswordHash, Role)
VALUES 
(1, 'Admin User', 'admin@communityconnect.com', 'admin123', 'Admin'),
(2, 'Default User', 'user@communityconnect.com', '$2y$12$mblawyo1N5rPz25DXe1sHe6.bsa1TJSp.AWftpEsnBu5Yl8t/DIkW', 'Resident');

INSERT INTO CommunityServices (ServiceID, Title, Description, EventDate, EventStartTime, EventEndTime, Location, Capacity, AdminID)
VALUES
(1, 'Neighbourhood Clean-Up Campaign', 'Join us to clean the neighbourhood and promote a cleaner community.', '2026-07-05', '09:00:00', '13:00:00', 'Klang Valley Park', 30, 1),

(2, 'Charity Donation Drive', 'Help collect and organize donation items for families in need.', '2026-07-10', '10:00:00', '14:00:00', 'Community Hall', 25, 1),
(3, 'Tree Planting Activity', 'Support SDG 11 by planting trees and improving green spaces.', '2026-07-15', '08:00:00', '12:00:00', 'Taman Community Area', 40, 1),
(4, 'Educational Workshop', 'A workshop to educate residents about sustainable living.', '2026-07-20', '13:00:00', '16:00:00', 'Harmony Learning Centre', 20, 1),
(5, 'Food Distribution Program', 'Assist in packing and distributing food supplies to low-income families in the community.', '2026-07-25', '09:00:00', '12:00:00', 'Community Food Bank', 20, 1),
(6, 'Recycling Awareness Campaign', 'Help educate residents about recycling practices and proper waste separation.', '2026-07-30', '10:00:00', '13:00:00', 'Eco Centre Hall', 25, 1),
(7, 'Senior Citizen Support Visit', 'Volunteer to visit senior citizens and assist with simple daily tasks and companionship activities.', '2026-08-03', '09:30:00', '12:30:00', 'Sunrise Elderly Home', 15, 1),
(8, 'Community Health Screening', 'Support a basic health screening event by assisting with registration and crowd management.', '2026-08-08', '08:00:00', '12:00:00', 'Community Clinic', 30, 1),
(9, 'Public Park Beautification', 'Help improve public spaces by painting benches, cleaning walkways, and organizing park facilities.', '2026-08-12', '08:30:00', '12:30:00', 'Harmony Public Park', 35, 1),
(10, 'Children Reading Program', 'Assist children with reading activities and help promote literacy in the local community.', '2026-08-18', '14:00:00', '17:00:00', 'Community Library', 18, 1),
(11, 'Disaster Relief Packing Drive', 'Help pack emergency relief kits containing food, water, and essential supplies for affected communities.', '2026-08-23', '10:00:00', '15:00:00', 'Relief Centre Warehouse', 40, 1),
(12, 'Community Safety Awareness Talk', 'Support an awareness session about neighbourhood safety, emergency contacts, and community cooperation.', '2026-08-28', '13:00:00', '16:00:00', 'Community Hall Room B', 25, 1);