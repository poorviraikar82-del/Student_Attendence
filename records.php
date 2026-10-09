
<?php
require "db.php";

$summary = $conn->query("
    SELECT
        s.id AS student_id,
        s.roll_no,
        s.name AS student_name,
        s.class_name,
        sub.id AS subject_id,
        sub.subject_code,
        sub.subject_name,
        sub.teacher_name,
        COUNT(a.id) AS marked_count,
        COALESCE(SUM(a.status = 'Present'), 0) AS present_count,
        COALESCE(SUM(a.status = 'Absent'), 0) AS absent_count,
        ROUND(
            100.0 * COALESCE(SUM(a.status = 'Present'), 0)
            / NULLIF(COUNT(a.id), 0), 1
        ) AS percentage
    FROM students s
    CROSS JOIN subjects sub
    LEFT JOIN lectures l ON l.subject_id = sub.id
    LEFT JOIN attendance a
        ON a.lecture_id = l.id AND a.student_id = s.id
    GROUP BY
        s.id, s.roll_no, s.name, s.class_name,
        sub.id, sub.subject_code, sub.subject_name, sub.teacher_name
    ORDER BY s.roll_no, sub.subject_name
");

$history = $conn->query("
    SELECT
        s.roll_no,
        s.name AS student_name,
        sub.subject_code,
        sub.subject_name,
        sub.teacher_name,
        l.lecture_date,
        l.period_number,
        l.start_time,
        a.status
    FROM attendance a
    JOIN students s ON s.id = a.student_id
    JOIN lectures l ON l.id = a.lecture_id
    JOIN subjects sub ON sub.id = l.subject_id
    ORDER BY l.lecture_date DESC, l.period_number, s.roll_no
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subject-wise Attendance Reports</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <h1>Attendance Reports</h1>
    <p>Subject-wise percentages and lecture history</p>
</header>

<nav>
    <a href="index.php">Dashboard</a>
    <a href="add_student.php">Add Student</a>
    <a href="subjects.php">Subjects & Teachers</a>
    <a href="attendance.php">Mark Attendance</a>
    <a href="records.php">Reports</a>
</nav>

<main>
<section class="panel">
    <h2>Student Attendance by Subject</h2>
    <p>Only saved attendance statuses are counted. Unmarked lectures
       are not included in the percentage.</p>

    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Roll No.</th>
                <th>Student</th>
                <th>Subject</th>
                <th>Teacher</th>
                <th>Present</th>
                <th>Absent</th>
                <th>Marked</th>
                <th>Percentage</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($summary->num_rows === 0): ?>
            <tr><td colspan="8">
                Add students and subjects to generate reports.
            </td></tr>
        <?php endif; ?>

        <?php while ($r = $summary->fetch_assoc()): ?>
            <tr>
                <td><?= e($r["roll_no"]) ?></td>
                <td><?= e($r["student_name"]) ?></td>
                <td><?= e($r["subject_code"] . " - " .
                    $r["subject_name"]) ?></td>
                <td><?= e($r["teacher_name"]) ?></td>
                <td><?= (int)$r["present_count"] ?></td>
                <td><?= (int)$r["absent_count"] ?></td>
                <td><?= (int)$r["marked_count"] ?></td>
                <td>
                    <?php if ($r["percentage"] === null): ?>
                        No records
                    <?php else: ?>
                        <strong><?= e($r["percentage"]) ?>%</strong>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="panel">
    <h2>Lecture Attendance History</h2>

    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Period</th>
                <th>Subject</th>
                <th>Teacher</th>
                <th>Roll No.</th>
                <th>Student</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($history->num_rows === 0): ?>
            <tr><td colspan="7">No attendance recorded yet.</td></tr>
        <?php endif; ?>

        <?php while ($r = $history->fetch_assoc()): ?>
            <tr>
                <td><?= e($r["lecture_date"]) ?></td>
                <td>
                    <?= (int)$r["period_number"] ?>
                    <?php if ($r["start_time"]): ?>
                        (<?= e($r["start_time"]) ?>)
                    <?php endif; ?>
                </td>
                <td><?= e($r["subject_code"] . " - " .
                    $r["subject_name"]) ?></td>
                <td><?= e($r["teacher_name"]) ?></td>
                <td><?= e($r["roll_no"]) ?></td>
                <td><?= e($r["student_name"]) ?></td>
                <td>
                    <span class="<?= $r["status"] === "Present"
                        ? "present" : "absent" ?>">
                        <?= e($r["status"]) ?>
                    </span>
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