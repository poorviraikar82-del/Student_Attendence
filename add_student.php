
<?php
require "db.php";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $roll = trim($_POST["roll_no"] ?? "");
    $name = trim($_POST["name"] ?? "");
    $class = trim($_POST["class_name"] ?? "");

    if ($roll === "" || $name === "" || $class === "") {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO students (roll_no, name, class_name)
             VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $roll, $name, $class);

        try {
            $stmt->execute();
            $message = "Student added successfully!";
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1062) {
                $error = "That roll number already exists.";
            } else {
                $error = "Could not add the student.";
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Student</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header><h1>Add Student</h1></header>

<nav>
    <a href="index.php">Dashboard</a>
    <a href="add_student.php">Add Student</a>
    <a href="subjects.php">Subjects & Teachers</a>
    <a href="attendance.php">Mark Attendance</a>
    <a href="records.php">Reports</a>
</nav>

<main>
<section class="panel">
    <h2>Student Details</h2>

    <?php if ($message): ?>
        <p class="success"><?= e($message) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <label for="roll_no">Roll Number / USN</label>
        <input id="roll_no" name="roll_no" maxlength="30" required>

        <label for="name">Student Name</label>
        <input id="name" name="name" maxlength="100" required>

        <label for="class_name">Class / Semester</label>
        <input id="class_name" name="class_name" maxlength="50"
               placeholder="BCA 2nd Year" required>

        <button type="submit">Add Student</button>
    </form>
</section>
</main>
</body>
</html>