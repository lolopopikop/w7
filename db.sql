CREATE TABLE application (
	  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	  fio VARCHAR(150) NOT NULL,
	  phone VARCHAR(20) NOT NULL,
	  email VARCHAR(100) NOT NULL,
	  birthdate DATE NOT NULL,
	  gender VARCHAR(10) NOT NULL,
	  biography TEXT,
	  contract TINYINT(1) NOT NULL
);

CREATE TABLE programming_languages (
	  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	  name VARCHAR(50) NOT NULL
);

CREATE TABLE application_languages (
	  application_id INT UNSIGNED NOT NULL,
	  language_id INT UNSIGNED NOT NULL,
	  PRIMARY KEY(application_id, language_id),
	  FOREIGN KEY (application_id) REFERENCES application(id),
	  FOREIGN KEY (language_id) REFERENCES programming_languages(id)
);
