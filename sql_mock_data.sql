# Create table statements
CREATE TABLE Rooms  (
	RoomID INT,
	FloorNumber INT NOT NULL,
	BedCount INT NOT NULL,
	Availability BOOLEAN NOT NULL,
	PRIMARY KEY (RoomID)
);

CREATE TABLE Patients  (
PatientID INT,
PatientName CHAR(20) NOT NULL,
PhoneNumber CHAR(20) NOT NULL UNIQUE,    
Gender CHAR(10),
DOB DATE NOT NULL,           	 
PRIMARY KEY (PatientID)               	 
);
           	 
CREATE TABLE LocatedIn (
	RoomID INT,
	PatientID INT UNIQUE,
	PRIMARY KEY (RoomID, PatientID),
	FOREIGN KEY (RoomID) REFERENCES Rooms(RoomID) ON DELETE CASCADE ON UPDATE CASCADE,
	FOREIGN KEY (PatientID) REFERENCES Patients(PatientID) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE RelativeContact (
	PatientID INT,
PhoneNumber CHAR(20) NOT NULL UNIQUE,    
	RelativeName CHAR(20),
	Relation CHAR(20) NOT NULL,
	FOREIGN KEY (PatientID) REFERENCES Patients(PatientID) ON DELETE CASCADE ON UPDATE CASCADE,
	PRIMARY KEY (PatientID, PhoneNumber)
);

CREATE TABLE Personnels (
	PersonnelName CHAR(20) NOT NULL,
	ExperienceYears INT,
	Salary REAL NOT NULL,
	StaffID INT,
	WorkingHours CHAR(30),
PhoneNumber CHAR(20) NOT NULL UNIQUE,    
PRIMARY KEY (StaffID)
);

CREATE TABLE Doctors(
	DoctorID INT,
	MedicalDegreeID CHAR(30) UNIQUE NOT NULL,
	Speciality CHAR(30),
	FOREIGN KEY (DoctorID) REFERENCES Personnels(StaffID) ON DELETE CASCADE ON UPDATE CASCADE,
	PRIMARY KEY (DoctorID)
);

CREATE TABLE Nurses(
	NurseID INT,
	FOREIGN KEY (NurseID) REFERENCES Personnels(StaffID) ON DELETE CASCADE ON UPDATE CASCADE,
	PRIMARY KEY (NurseID)
);

CREATE TABLE AssignedTo (
	DoctorID INT NOT NULL,
	NurseID INT NOT NULL,
	Shift CHAR(30),
	FOREIGN KEY (DoctorID) REFERENCES Doctors(DoctorID) ON DELETE CASCADE ON UPDATE CASCADE,
	FOREIGN KEY (NurseID) REFERENCES Nurses(NurseID) ON DELETE CASCADE ON UPDATE CASCADE,
	PRIMARY KEY (NurseID)
);

CREATE TABLE Appointment (
PatientID INT NOT NULL,
DoctorID INT NOT NULL,
AppointmentDate DATE,
Speciality CHAR(30)  NOT NULL,
FOREIGN KEY (PatientID) REFERENCES Patients(PatientID) ON DELETE CASCADE ON UPDATE CASCADE,
FOREIGN KEY (DoctorID) REFERENCES Doctors(DoctorID) ON DELETE CASCADE ON UPDATE CASCADE,
PRIMARY KEY (PatientID, DoctorID, AppointmentDate)
);    
    
CREATE TABLE Medicines (    
	MedicineID INT NOT NULL,
	MedicineName CHAR(20),
	Price REAL NOT NULL,
	Dosage REAL NOT NULL,
	Manufacturer CHAR(20),
	PRIMARY KEY (MedicineID)
);
    
CREATE TABLE Bills (
	BillID INT NOT NULL,
	BillDate DATE NOT NULL,
	PaymentStatus CHAR(20) NOT NULL,
	ServiceCost REAL NOT NULL DEFAULT 0,
	MedicineCost REAL NOT NULL DEFAULT 0,
	TotalCost REAL NOT NULL DEFAULT 0,
	PRIMARY KEY (BillID)
);

CREATE TABLE Insurance (
	InsuranceID INT NOT NULL,
	CompanyName CHAR(20) NOT NULL,
	PolicyNumber INT NOT NULL,
	InsuranceType CHAR(20) NOT NULL,
	ExpiryDate DATE NOT NULL,
PRIMARY KEY (InsuranceID)
);

CREATE TABLE Covers (
	BillID INT NOT NULL,
	InsuranceID INT NOT NULL,
	CoverageAmount REAL NOT NULL,
	FOREIGN KEY (BillID) REFERENCES Bills(BillID) ON DELETE CASCADE ON UPDATE CASCADE,
	FOREIGN KEY (InsuranceID) REFERENCES Insurance(InsuranceID) ON DELETE CASCADE ON UPDATE CASCADE,
	PRIMARY KEY (BillID)
);

CREATE TABLE Prescription (
	PressDate DATE NOT NULL,
	PressID INT NOT NULL UNIQUE,
	PRIMARY KEY (PressID)
);

CREATE TABLE Has_Pres (
	PatientID INT NOT NULL,
	PressID INT NOT NULL,
	PRIMARY KEY (PressID),
	FOREIGN KEY (PatientID) REFERENCES Patients(PatientID) ON DELETE CASCADE ON UPDATE CASCADE,
	FOREIGN KEY (PressID) REFERENCES Prescription(PressID) ON DELETE CASCADE ON UPDATE CASCADE
);   	 

CREATE TABLE Included (
	MedicineID INT NOT NULL,
	PressID INT NOT NULL,
	PRIMARY KEY (PressID, MedicineID),
	FOREIGN KEY (MedicineID) REFERENCES Medicines(MedicineID) ON DELETE CASCADE ON UPDATE CASCADE,
	FOREIGN KEY (PressID) REFERENCES Prescription(PressID) ON DELETE CASCADE ON UPDATE CASCADE
); 
CREATE TABLE Prescribes (
	MedicalDegreeID CHAR(30) NOT NULL,
	PressID INT NOT NULL,
	PRIMARY KEY (PressID),
	FOREIGN KEY (MedicalDegreeID) REFERENCES Doctors(MedicalDegreeID) ON DELETE CASCADE ON UPDATE CASCADE,
	FOREIGN KEY (PressID) REFERENCES Prescription(PressID) ON DELETE CASCADE ON UPDATE CASCADE
); 
CREATE TABLE Bill_Pres(
	BillID INT NOT NULL,
	PressID INT NOT NULL,
	PRIMARY KEY (PressID),
	FOREIGN KEY (BillID) REFERENCES Bills(BillID) ON DELETE CASCADE ON UPDATE CASCADE,
	FOREIGN KEY (PressID) REFERENCES Prescription(PressID) ON DELETE CASCADE ON UPDATE CASCADE
); 


# Triggers

DELIMITER //
CREATE TRIGGER change_availability_rooms
BEFORE INSERT ON LocatedIn
FOR EACH ROW
BEGIN
    DECLARE room_count INT;
    DECLARE capacity INT;

    -- Number of patients exist in the room
    SELECT COUNT(*) INTO room_count
    FROM LocatedIn
    WHERE RoomID = NEW.RoomID;

    -- Find capacity of the room
    SELECT BedCount INTO capacity
    FROM Rooms
    WHERE RoomID = NEW.RoomID;

    -- if room is not available for a new patient
    IF room_count >= capacity THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot add more patients: Room is already full';
    END IF;

    -- if room will be unavailable with new insert
    IF room_count + 1 = capacity THEN
        UPDATE Rooms
        SET Availability = 0
        WHERE RoomID = NEW.RoomID;
    END IF;
END //
DELIMITER ;


DELIMITER //
CREATE TRIGGER medicine_cost_adder
AFTER INSERT ON Included
FOR EACH ROW
BEGIN

DECLARE Mprice INT;
DECLARE bill_ID INT;
SELECT Price INTO Mprice
FROM Medicines
WHERE MedicineID = NEW.MedicineID;

SELECT BillID INTO bill_ID
FROM Bill_Pres
WHERE PressID = NEW.PressID;

UPDATE Bills
SET MedicineCost = MedicineCost + MPrice,
        TotalCost = TotalCost + MPrice
WHERE BillID = bill_ID;
END //
DELIMITER;

DELIMITER //

CREATE TRIGGER prevent_duplicate_appointments
BEFORE INSERT ON Appointment
FOR EACH ROW
BEGIN
    DECLARE existing_count INT;
    DECLARE doctorSpeciality CHAR(30);
	DECLARE error_message VARCHAR(255);

	SELECT Speciality into doctorSpeciality
    FROM Doctors
    WHERE NEW.DoctorID = DoctorID;
   
	IF NEW.Speciality != doctorSpeciality THEN
	SIGNAL SQLSTATE '45000' 
	SET MESSAGE_TEXT = 'This doctor has a different speciality!';

	
    ELSE 
		-- Check if the same patient has an appointment on the same date
		SELECT COUNT(*) INTO existing_count
		FROM Appointment
		WHERE PatientID = NEW.PatientID AND AppointmentDate = NEW.AppointmentDate AND 
	Speciality = NEW.Speciality ;

		-- If you already have an appointment, stop the process
		IF existing_count > 0 THEN
			SET error_message = CONCAT('This patient already has a/an ', doctorSpeciality, ' appointment on this date!');
			SIGNAL SQLSTATE '45000' 
			SET MESSAGE_TEXT = error_message;
		END IF;
	END IF;
END //

DELIMITER ;

# Procedures

DELIMITER //
CREATE PROCEDURE display_prescription(IN patientID INT, IN theYear INT)
BEGIN
    SELECT 
        P.PressID, 
        PT.PatientID,
        PT.PatientName,
        D.DoctorID,
        PS.PersonnelName AS DoctorName,
        D.Speciality,
        MED.MedicineName,
        MED.Dosage,
		P.PressDate
    FROM Has_Pres HP
    JOIN Prescription P ON HP.PressID = P.PressID
    JOIN Prescribes W ON P.PressID = W.PressID
    JOIN Doctors D ON W.MedicalDegreeID = D.MedicalDegreeID
	JOIN Personnels PS ON PS.StaffID = D.DoctorID
    JOIN Patients PT ON PT.PatientID = HP.PatientID
    JOIN Included INC ON INC.PressID = HP.PressID
    JOIN Medicines MED ON MED.MedicineID = INC.MedicineID
    WHERE HP.PatientID = patientID 
      AND YEAR(P.PressDate) = theYear;
END //
DELIMITER //

DELIMITER //
CREATE PROCEDURE available_bed_number(IN Room INT)
BEGIN
    DECLARE freeBed INT;
    DECLARE totalBed INT;
    DECLARE busyBed INT;

    SELECT BedCount INTO totalBed
    FROM Rooms
    WHERE RoomID = Room;

    SELECT COUNT(*) INTO busyBed
    FROM LocatedIn
    WHERE RoomID = Room;

    SET freeBed = totalBed - busyBed;
    
    SELECT freeBed;
END //

DELIMITER ;

DELIMITER //
CREATE PROCEDURE display_doc_appointments(IN docID INT, IN startDate DATE, IN endDate DATE)
	BEGIN
	SELECT Pers.StaffID AS DoctorID, Pers.PersonnelName, P.PatientID, P.PatientName, A.AppointmentDate
	FROM Appointment A, Patients P, Personnels Pers
	WHERE A.DoctorID = docID AND A.AppointmentDate > startDate AND A.AppointmentDate < endDate AND Pers.StaffID = docID AND P.PatientID = A.PatientID;

END //
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE choose_room(IN p_room_id INT, IN p_patient_id INT, OUT p_message VARCHAR(255))
	BEGIN
		DECLARE patient_exists INT DEFAULT 0;
		DECLARE already_assigned INT DEFAULT 0;
		DECLARE EXIT HANDLER FOR SQLEXCEPTION
		BEGIN
			SET p_message = '❌ SQL Error occurred.';
			ROLLBACK;
		END;
		START TRANSACTION;
		SELECT COUNT(*) INTO patient_exists
		FROM Patients
		WHERE PatientID = p_patient_id;
		IF patient_exists = 0 THEN
			SET p_message = CONCAT('❌ PatientID ', p_patient_id, ' not found in Patients table.');
			ROLLBACK;
		ELSE
			SELECT COUNT(*) INTO already_assigned
			FROM LocatedIn
			WHERE PatientID = p_patient_id;
			IF already_assigned > 0 THEN
				SET p_message = '❌ This patient is already assigned to a room.';
				ROLLBACK;
			ELSE
				INSERT INTO LocatedIn (RoomID, PatientID)
				VALUES (p_room_id, p_patient_id);
				SET p_message = CONCAT('✅ Patient assigned successfully to Room ', p_room_id);
				COMMIT;
			END IF;
		END IF;
	END$$
DELIMITER ;

# Mock Data
INSERT INTO Rooms (RoomID, FloorNumber, BedCount, Availability) VALUES
(1, 53, 3, true),
(2, 2, 1, true),
(3, 19, 10, true),
(4, 90, 6, true),  
(5, 9, 5, true),
(6, 8, 2, true),
(7, 54, 10, true),
(8, 80, 5, true),
(9, 74, 8, true),
(10, 53, 3, true);

INSERT INTO Patients (PatientID, PatientName, PhoneNumber, Gender, DOB) VALUES
(296049359, 'Bertine Silvers', '584-999-6047', 'Female', '1989-01-07'),
(157435238, 'Bob Todman', '123-128-7774', 'Male', '1948-12-21'),
(40441509, 'Wyndham Allardyce', '196-242-5680', 'Male', '1978-07-04'),
(797224685, 'Muire Buddleigh', '925-685-1996', 'Female', '1950-10-30'),
(884931996, 'Gerardo Elcom', '883-994-3736', 'Male', '1933-11-05'),
(572697154, 'Joellyn Sproston', '291-551-0270', 'Female', '1935-12-04'),
(67218682, 'Bordie Attewill', '211-705-7222', 'Male', '1960-06-9'),
(90401050, 'Shaylyn Shilladay', '635-325-6214', 'Polygender', '1974-09-11'),
(801325610, 'Linet Altoft', '943-893-0668', 'Female', '1999-11-06'),
(105950837, 'Vinni O''Crigan', '378-966-5801', 'Female', '1944-03-04'),
(12312312, 'Johny Bravo', '328-966-5821', 'Male', '1877-12-20'),
(45678965, 'Mbappe', '378-946-5501', 'Male', '1995-01-01'),
(55678876, 'Cristiano Suarez', '178-966-6801', 'Male', '1980-04-01'),
(5678999, 'Salah Junior', '278-936-5801', 'Male', '1984-06-20'),
(77776665, 'Guler Arda', '309-966-5701', 'Male', '2005-03-08'),
(435856745, 'Ahmet Dayi', '998-966-7801', 'Male', '1984-02-05');


INSERT INTO LocatedIn (RoomID, PatientID) VALUES
(1, 296049359),
(3, 40441509),
(4, 797224685),
(7, 67218682),
(9, 801325610),
(3, 12312312),
(4, 45678965),
(5, 55678876),
(1, 5678999),
(9, 435856745);

INSERT INTO RelativeContact (PatientID, PhoneNumber, RelativeName, Relation) VALUES
(105950837, '897-200-0891', 'Bertine Silvers', 'sister'),
(157435238, '317-534-9121', 'Bob Todman', 'mother'),
(40441509, '163-720-9360', 'Wyndham Allardyce', 'father'),
(797224685, '772-495-0447', 'Muire Buddleigh', 'father'),
(884931996, '844-607-4177', 'Gerardo Elcom', 'cousin'),
(572697154, '860-112-9098', 'Joellyn Sproston', 'niece'),
(67218682, '502-387-8541', 'Bordie Attewill', 'brother'),
(90401050, '577-846-8254', 'Shaylyn Shilladay', 'nephew'),
(801325610, '322-279-6213', 'Linet Altoft', 'mother'),
(105950837, '166-239-5791', 'Vinni O''Crigan', 'nephew'),
(12312312, '266-249-5491', 'Khan Bravo', 'father' ),
(45678965, '366-269-9791', 'Vini JR', 'friend'),
(55678876, '766-859-5791', 'Bella Hadid', 'wife'),
(5678999, '486-259-1791', 'Ali Erbas', 'father'),
(77776665, '469-259-5721', 'Ali Koc', 'uncle' ),
(435856745, '466-359-4791', 'Fatma Yenge', 'sister');

INSERT INTO Personnels (PersonnelName, ExperienceYears, Salary, StaffID, WorkingHours, PhoneNumber) VALUES
('Morganica Whitrod', 24, 282859, 1362, 56, '920-573-7070'),
('Haley Deedes', 28, 403751, 3561, 60, '551-360-9339'),
('Barrie Frounks', 23, 214038, 611, 78, '648-474-3227'),
('Quill Hanigan', 24, 149029, 1611, 70, '183-586-1544'),
('Martyn Sim', 7, 101612, 684, 80, '421-123-4120'),
('Kip Flement', 20, 371638, 1824, 80, '967-516-8245'),
('Gretta Bullman', 49, 201168, 2240, 55, '628-779-0109'),
('Roslyn Whotton', 11, 333863, 3331, 72, '616-452-7583'),
('Leontine Bruhke', 28, 287268, 920, 72, '496-683-4672'),
('Clarine Franzotto', 31, 403374, 862, 72, '479-559-3919'),
('Liuka Lewsy', 37, 263489, 2593, 80, '399-318-2793'),
('Cari Ivie', 34, 347829, 1083, 80, '339-494-2349'),
('Elfie Iacobucci', 37, 496694, 1132, 72, '785-909-8127'),
('Booth Rickardsson', 21, 276228, 609, 80, '260-954-0583'),
('Katlin Cumber', 48, 190397, 3787, 80, '997-203-2063'),
('Valentine Lumby', 2, 226910, 3708, 80, '192-554-4724'),
('Alvina Frankom', 39, 102037, 890, 72, '660-986-3318'),
('Daisy Sidsaff', 18, 99601, 1351, 80, '719-294-5436'),
('Letitia Grzelczak', 10, 136122, 2601, 70, '445-446-0610'),
('Luz Blenkharn', 29, 136209, 2666, 80, '839-784-0149');


INSERT INTO Doctors (DoctorID, MedicalDegreeID, Speciality) VALUES
(1362, 'cfK6kGQxc', 'Dermatology'),
(3561, 'ja5F74Dj9', 'Dermatology'),
(611, '6xSHkH3sh', 'Neurology'),
(1611, 'io1Po5N4l', 'Pathology'),
(684, '2tQJpNE12', 'Pediatrics'),
(1824, '9k7VwSSvc', 'Psychiatry'),
(2240, 'ygZ5jLRrz', 'Radiotology'),
(3331, 'ojFRtND5y', 'Urology'),
(920, '0vBSq3Ptv', 'Dermatology'),
(862, 'faA97LNf6', 'Anesthesiology');

insert into Nurses (NurseID) values (2593),
(1083), (1132), (609), (3787), (3708), (890), (1351), (2601), (2666);

insert into AssignedTo (DoctorID, NurseID, Shift) values (1362, 2593,'16:00-23:59'),
(3561, 1083,'00:00-08:00'),
(611, 1132,'00:00-08:00'),
(1611, 609,'16:00-23:59'),
(684, 3787,'12:00-20:00'),
(1824, 3708,'12:00-20:00'),
(2240, 890, '16:00-23:59'),
(3331, 1351,'12:00-20:00'),  
(920, 2601,'00:00-08:00'),            
(862, 2666, '16:00-23:59');

insert into Appointment (PatientID, DoctorID, AppointmentDate, Speciality) values 
(296049359, 1362, '2025-12-03', 'Dermatology'),
(157435238, 3561, '2025-06-06', 'Dermatology'),
(40441509, 611, '2025-10-27', 'Neurology'),
(797224685, 1611, '2025-03-24', 'Pathology'),
(884931996, 684, '2025-03-02', 'Pediatrics'),
(572697154, 1824, '2025-09-22', 'Psychiatry'),
(67218682, 2240, '2025-07-08', 'Radiotology'),
(90401050, 3331, '2025-12-11', 'Urology'),
(801325610, 920, '2025-08-04', 'Dermatology'),
(105950837, 862, '2025-01-26', 'Anesthesiology'),
(40441509, 611, '2025-11-27', 'Neurology');

INSERT INTO Medicines (MedicineID, MedicineName, Price, Dosage, Manufacturer) VALUES 
(155, 'Lipitor', 150.75, 1, 'Pfizer'),
(5040, 'Nexium', 120, 2, 'AstraZeneca'),
(1171, 'Plavix', 130, 2, 'Sanofi'),
(714, 'Advair Diskus', 130.29, 1, 'GlaxoSmithKline'),
(80, 'Abilify', 140, 3, 'Otsuka America'),
(275, 'Seroquel', 135, 2, 'AstraZeneca'),
(777, 'Crestor', 155.14, 3, 'AstraZeneca'),
(3223, 'Cymbalta', 320, 2, 'Eli Lilly'),
(3799, 'Humira', 80, 1, 'AbbVie'),
(425, 'Enbrel', 35.77, 2, 'Immunex');

INSERT INTO Bills (BillID, BillDate, PaymentStatus) VALUES 
(1001, '2024-12-21', 'Paid'),
(1002, '2024-06-02', 'Paid'),
(1003, '2024-07-09', 'Pending'),
(1004, '2024-03-29', 'Unpaid'),
(1005, '2025-02-24', 'Unpaid'),
(1006, '2025-01-15', 'Pending'),
(1007, '2024-04-23', 'Pending'),
(1008, '2024-03-15', 'Paid'),
(1009, '2024-04-30', 'Unpaid'),
(1010, '2024-11-12', 'Paid');

INSERT INTO Insurance (InsuranceID, CompanyName, PolicyNumber, InsuranceType, ExpiryDate) VALUES 
(5001, 'MetLife', 233287, 'Home', '2025-10-23'),
(5002, 'Allianz', 233283, 'Auto', '2029-11-25'),
(5003, 'MetLife', 456214, 'Auto', '2026-10-22'),
(5004, 'Axa', 456578, 'Life', '2029-03-02'),
(5005, 'Allianz', 154687, 'Home', '2026-03-04'),
(5006, 'Axa', 456794, 'Travel', '2027-06-12'),
(5007, 'MetLife', 125654, 'Home', '2028-07-25'),
(5008, 'Axa', 745164, 'Auto', '2026-11-25'),
(5009, 'Axa', 741255, 'Life', '2029-01-05'),
(5010, 'Allianz', 486544, 'Life', '2026-10-08');

INSERT INTO Covers (BillID, InsuranceID, CoverageAmount) VALUES 
(1001, 5001, 50.7),
(1002, 5002, 20.45),
(1003, 5003, 40.89),
(1004, 5004, 87.3),
(1005, 5005, 90.9),
(1006, 5006, 70.89),
(1007, 5007, 30),
(1008, 5008, 20),
(1009, 5009, 98.78),
(1010, 5010, 79.5);

INSERT INTO Prescription (PressID, PressDate) VALUES 
(123123, '2025-09-26'),
(232323, '2025-04-13'),
(23445, '2025-07-27'),
(53467546, '2025-04-10'),
(345, '2025-03-06'),
(3454,'2025-11-03'),
(5436, '2025-05-24'),
(4354, '2025-10-14'),
(7777, '2025-03-25'),
(656, '2025-02-18');

INSERT INTO Has_Pres ( PressID, PatientID) VALUES
(123123, 296049359),
(232323, 157435238),
(23445, 40441509),
(53467546, 797224685),
(345, 884931996),
(3454,572697154),
(5436, 67218682),
(4354, 90401050),
(7777, 801325610),
(656, 105950837);

INSERT INTO Prescribes ( PressID, MedicalDegreeID) VALUES
(123123, 'cfK6kGQxc'),
(232323, '2tQJpNE12'),
(23445, 'cfK6kGQxc'),
(53467546, '2tQJpNE12'),
(345, 'ojFRtND5y'),
(3454,'cfK6kGQxc'),
(5436, 'faA97LNf6'),
(4354, 'ojFRtND5y'),
(7777, '2tQJpNE12'),
(656, 'faA97LNf6');

INSERT INTO Bill_Pres ( PressID, BillID) VALUES
(123123, 1001),
(232323, 1002),
(23445, 1003),
(53467546, 1007),
(345, 1010),
(3454,1005),
(5436, 1001),
(4354, 1001),
(7777, 1004),
(656, 1009);

INSERT INTO Included ( PressID, MedicineID) VALUES
(123123, 155),
(123123, 5040),
(123123, 1171),
(232323, 5040),
(23445, 1171),
(123123, 714),
(53467546, 714),
(345, 80),
(3454, 80),
(3454,275),
(5436, 1171),
(7777,275),
(4354, 3223),
(7777, 1171),
(656, 1171),
(656, 3223);

# Mock data for test cases
INSERT INTO personnels(PersonnelName, ExperienceYears, Salary, StaffID, WorkingHours, PhoneNumber) VALUES ("Barry Allen", 20, 2040, 612, 80, '225-225-2525');
INSERT INTO doctors(DoctorID, MedicalDegreeID, Speciality) VALUES (612, "7xSHkH3sh", "Neurology");





