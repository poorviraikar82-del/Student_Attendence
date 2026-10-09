
<?php
require "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_id"])) {
    $id = filter_var($_POST["delete_id"], FILTER_VALIDATE_INT);

    if ($id) {
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: index.php");
    exit;
}

$total = $conn->query(
    "SELECT COUNT(*) AS n FROM students"
)->fetch_assoc()["n"];

$subjectCount = $conn->query(
    "SELECT COUNT(*) AS n FROM subjects"
)->fetch_assoc()["n"];

$lectureCount = $conn->query(
    "SELECT COUNT(*) AS n FROM lectures"
)->fetch_assoc()["n"];

$students = $conn->query(
    "SELECT id, roll_no, name, class_name
     FROM students ORDER BY roll_no"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Attendance Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <h1>Student Attendance System</h1>
    <p>Manage students, subjects, teachers and lectures</p>
</header>

<nav>
    <a href="index.php">Dashboard</a>
    <a href="add_student.php">Add Student</a>
    <a href="subjects.php">Subjects & Teachers</a>
    <a href="attendance.php">Mark Attendance</a>
    <a href="records.php">Reports</a>
</nav>

<main>
    <div class="cards">
        <section class="summary">
            <span>Total Students</span>
            <h2><?= (int)$total ?></h2>
        </section>
        <section class="summary">
            <span>Total Subjects</span>
            <h2><?= (int)$subjectCount ?></h2>
        </section>
        <section class="summary">
            <span>Scheduled Lectures</span>
            <h2><?= (int)$lectureCount ?></h2>
        </section>
    </div>

    <section class="panel">
        <h2>Registered Students</h2>
        <p><a class="button-link" href="add_student.php">
            + Add Student
        </a></p>

        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Roll Number</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($students->num_rows === 0): ?>
                <tr><td colspan="4">No students added yet.</td></tr>
            <?php endif; ?>

            <?php while ($s = $students->fetch_assoc()): ?>
                <tr>
                    <td><?= e($s["roll_no"]) ?></td>
                    <td><?= e($s["name"]) ?></td>
                    <td><?= e($s["class_name"]) ?></td>
                    <td>
                        <form method="post"
                              onsubmit="return confirm('Delete this student and their attendance?');">
                            <input type="hidden" name="delete_id"
                                   value="<?= (int)$s["id"] ?>">
                            <button class="danger" type="submit">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </section>
</main>
</body>
</html>