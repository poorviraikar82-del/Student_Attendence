
<?php
require "db.php";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $code = trim($_POST["subject_code"] ?? "");
    $name = trim($_POST["subject_name"] ?? "");
    $teacher = trim($_POST["teacher_name"] ?? "");

    if ($code === "" || $name === "" || $teacher === "") {
        $error = "Please complete all subject details.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO subjects
             (subject_code, subject_name, teacher_name)
             VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $code, $name, $teacher);

        try {
            $stmt->execute();
            $message = "Subject and teacher saved successfully!";
        } catch (mysqli_sql_exception $ex) {
            $error = $ex->getCode() === 1062
                ? "That subject code already exists."
                : "Could not save the subject.";
        }
        $stmt->close();
    }
}

$subjects = $conn->query(
    "SELECT s.id, s.subject_code, s.subject_name, s.teacher_name,
            COUNT(l.id) AS lecture_count
     FROM subjects s
     LEFT JOIN lectures l ON l.subject_id = s.id
     GROUP BY s.id, s.subject_code, s.subject_name, s.teacher_name
     ORDER BY s.subject_name"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subjects and Teachers</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header><h1>Subjects & Teachers</h1></header>

<nav>
    <a href="index.php">Dashboard</a>
    <a href="add_student.php">Add Student</a>
    <a href="subjects.php">Subjects & Teachers</a>
    <a href="attendance.php">Mark Attendance</a>
    <a href="records.php">Reports</a>
</nav>

<main>
<section class="panel">
    <h2>Add Subject</h2>

    <?php if ($message): ?>
        <p class="success"><?= e($message) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <label for="subject_code">Subject Code</label>
        <input id="subject_code" name="subject_code"
               maxlength="30" placeholder="BCS401" required>

        <label for="subject_name">Subject Name</label>
        <input id="subject_name" name="subject_name"
               maxlength="100" placeholder="Java Programming" required>

        <label for="teacher_name">Assigned Teacher</label>
        <input id="teacher_name" name="teacher_name"
               maxlength="100" placeholder="Teacher's full name" required>

        <button type="submit">Save Subject</button>
    </form>
</section>

<section class="panel">
    <h2>Registered Subjects</h2>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Subject</th>
                <th>Assigned Teacher</th>
                <th>Lectures</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($subjects->num_rows === 0): ?>
            <tr><td colspan="4">No subjects registered yet.</td></tr>
        <?php endif; ?>

        <?php while ($s = $subjects->fetch_assoc()): ?>
            <tr>
                <td><?= e($s["subject_code"]) ?></td>
                <td><?= e($s["subject_name"]) ?></td>
                <td><?= e($s["teacher_name"]) ?></td>
                <td><?= (int)$s["lecture_count"] ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</section>
</main>
</body>
</html>