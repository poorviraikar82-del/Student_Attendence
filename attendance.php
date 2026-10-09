
<?php
require "db.php";

function validDate($date) {
    if (!is_string($date) ||
        !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
        return false;
    }

    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

$subjects = $conn->query(
    "SELECT id, subject_code, subject_name, teacher_name
     FROM subjects ORDER BY subject_name"
)->fetch_all(MYSQLI_ASSOC);

$subjectId = filter_var(
    $_POST["subject_id"] ?? $_GET["subject_id"] ?? "",
    FILTER_VALIDATE_INT
);
$date = $_POST["lecture_date"] ?? $_GET["date"] ?? date("Y-m-d");

if (!validDate($date)) {
    $date = date("Y-m-d");
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if (!$subjectId || !in_array(
        $subjectId,
        array_column($subjects, "id"),
        false
    )) {
        $error = "Please select a valid subject.";
    } elseif ($action === "create_lecture") {
        $period = filter_var(
            $_POST["period_number"] ?? "",
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1, "max_range" => 20]]
        );

        $start = trim($_POST["start_time"] ?? "");
        $end = trim($_POST["end_time"] ?? "");

        $start = $start === "" ? null : $start;
        $end = $end === "" ? null : $end;

        $validTime = static function ($t) {
            return $t === null ||
                (is_string($t) &&
                 preg_match('/^\d{2}:\d{2}$/', $t) &&
                 (int)substr($t, 0, 2) < 24 &&
                 (int)substr($t, 3, 2) < 60);
        };

        if (!$period || !$validTime($start) || !$validTime($end)) {
            $error = "Enter a valid period number and times.";
        } elseif ($start !== null && $end !== null && $end <= $start) {
            $error = "End time must be later than start time.";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO lectures
                 (subject_id, lecture_date, period_number, start_time, end_time)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "isiss", $subjectId, $date, $period, $start, $end
            );

            try {
                $stmt->execute();
                $lectureId = $conn->insert_id;
                $stmt->close();

                header(
                    "Location: attendance.php?subject_id=" .
                    $subjectId . "&date=" . urlencode($date) .
                    "&lecture_id=" . $lectureId
                );
                exit;
            } catch (mysqli_sql_exception $ex) {
                $stmt->close();
                $error = $ex->getCode() === 1062
                    ? "That period already exists for this subject and date."
                    : "Could not create the lecture.";
            }
        }
    } elseif ($action === "save_attendance") {
        $lectureId = filter_var(
            $_POST["lecture_id"] ?? "",
            FILTER_VALIDATE_INT
        );

        $stmt = $conn->prepare(
            "SELECT id FROM lectures
             WHERE id = ? AND subject_id = ? AND lecture_date = ?"
        );
        $stmt->bind_param("iis", $lectureId, $subjectId, $date);
        $stmt->execute();
        $lecture = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$lecture) {
            $error = "Select a valid lecture before saving attendance.";
        } else {
            $students = $conn->query(
                "SELECT id FROM students"
            )->fetch_all(MYSQLI_ASSOC);

            $statuses = $_POST["status"] ?? [];
            $expectedIds = array_map(
                "intval", array_column($students, "id")
            );
            $submittedIds = array_map("intval", array_keys($statuses));

            sort($expectedIds);
            sort($submittedIds);

            if ($expectedIds !== $submittedIds) {
                $error = "Student list changed or attendance is incomplete. Reload the page.";
            } else {
                $conn->begin_transaction();

                try {
                    $stmt = $conn->prepare(
                        "INSERT INTO attendance (student_id, lecture_id, status)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE status = VALUES(status)"
                    );

                    foreach ($statuses as $id => $statusValue) {
                        $studentId = (int)$id;

                        if (!in_array($statusValue, ["Present", "Absent"], true)) {
                            throw new RuntimeException("Invalid status.");
                        }

                        $status = $statusValue;
                        $stmt->bind_param(
                            "iis", $studentId, $lectureId, $status
                        );
                        $stmt->execute();
                    }

                    $stmt->close();
                    $conn->commit();
                    $message = "Attendance saved successfully.";
                } catch (Throwable $ex) {
                    $conn->rollback();
                    $error = "Could not save attendance. Please reload and try again.";
                }
            }
        }
    }
}

$selectedSubject = null;
foreach ($subjects as $s) {
    if ((int)$s["id"] === (int)$subjectId) {
        $selectedSubject = $s;
        break;
    }
}

$lectures = [];
$students = [];
$selectedLectureId = filter_var(
    $_GET["lecture_id"] ?? $_POST["lecture_id"] ?? "",
    FILTER_VALIDATE_INT
);

if ($selectedSubject) {
    $stmt = $conn->prepare(
        "SELECT id, period_number, start_time, end_time
         FROM lectures
         WHERE subject_id = ? AND lecture_date = ?
         ORDER BY period_number"
    );
    $stmt->bind_param("is", $subjectId, $date);
    $stmt->execute();
    $lectures = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($selectedLectureId) {
        $stmt = $conn->prepare(
            "SELECT id FROM lectures
             WHERE id = ? AND subject_id = ? AND lecture_date = ?"
        );
        $stmt->bind_param(
            "iis", $selectedLectureId, $subjectId, $date
        );
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $selectedLectureId = null;
        }
        $stmt->close();
    }

    $stmt = $conn->prepare(
        "SELECT s.id, s.roll_no, s.name, a.status
         FROM students s
         LEFT JOIN attendance a
           ON a.student_id = s.id AND a.lecture_id = ?
         ORDER BY s.roll_no"
    );
    $stmt->bind_param("i", $selectedLectureId);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lecture Attendance</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <h1>Lecture-wise Attendance</h1>
    <p>Record attendance separately for every period</p>
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
    <h2>Select Subject and Date</h2>

    <?php if ($message): ?>
        <p class="success"><?= e($message) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="get">
        <label for="subject_id">Subject</label>
        <select id="subject_id" name="subject_id" required>
            <option value="">Choose a subject</option>
            <?php foreach ($subjects as $s): ?>
                <option value="<?= (int)$s["id"] ?>"
                    <?= (int)$subjectId === (int)$s["id"] ? "selected" : "" ?>>
                    <?= e($s["subject_code"] . " - " .
                        $s["subject_name"] . " (" . $s["teacher_name"] . ")") ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="date">Lecture Date</label>
        <input type="date" id="date" name="date"
               value="<?= e($date) ?>" required>
        <button type="submit">Load Subject</button>
    </form>
</section>

<?php if (!$subjects): ?>
    <section class="panel">
        No subjects registered. <a href="subjects.php">Add a subject first</a>.
    </section>
<?php elseif ($selectedSubject): ?>
    <section class="panel">
        <h2><?= e($selectedSubject["subject_name"]) ?></h2>
        <p><strong>Teacher:</strong>
            <?= e($selectedSubject["teacher_name"]) ?></p>
        <p><strong>Date:</strong> <?= e($date) ?></p>

        <h3>Create a Lecture / Period</h3>
        <form method="post">
            <input type="hidden" name="action" value="create_lecture">
            <input type="hidden" name="subject_id"
                   value="<?= (int)$subjectId ?>">
            <input type="hidden" name="lecture_date" value="<?= e($date) ?>">

            <label for="period_number">Period Number</label>
            <input type="number" id="period_number" name="period_number"
                   min="1" max="20" required>

            <label for="start_time">Start Time (optional)</label>
            <input type="time" id="start_time" name="start_time">

            <label for="end_time">End Time (optional)</label>
            <input type="time" id="end_time" name="end_time">

            <button type="submit">Create Lecture</button>
        </form>

        <h3>Lectures on <?= e($date) ?></h3>
        <?php if (!$lectures): ?>
            <p>No lectures created for this subject and date.</p>
        <?php else: ?>
            <div class="lecture-list">
            <?php foreach ($lectures as $l): ?>
                <a class="lecture-link"
                   href="attendance.php?subject_id=<?= (int)$subjectId ?>&date=<?= e($date) ?>&lecture_id=<?= (int)$l["id"] ?>">
                    Period <?= (int)$l["period_number"] ?>
                    (<?= e($l["start_time"] ?: "Time not set") ?>)
                    <?= (int)$selectedLectureId === (int)$l["id"]
                        ? " — Selected" : "" ?>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($selectedLectureId): ?>
    <section class="panel">
        <h2>Mark Student Attendance</h2>
        <form method="post">
            <input type="hidden" name="action" value="save_attendance">
            <input type="hidden" name="subject_id"
                   value="<?= (int)$subjectId ?>">
            <input type="hidden" name="lecture_date" value="<?= e($date) ?>">
            <input type="hidden" name="lecture_id"
                   value="<?= (int)$selectedLectureId ?>">

            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Roll No.</th>
                        <th>Student</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$students): ?>
                    <tr><td colspan="3">
                        Add students before marking attendance.
                    </td></tr>
                <?php endif; ?>

                <?php foreach ($students as $s): ?>
                    <tr>
                        <td><?= e($s["roll_no"]) ?></td>
                        <td><?= e($s["name"]) ?></td>
                        <td>
                            <select name="status[<?= (int)$s["id"] ?>]">
                                <option value="Present"
                                    <?= ($s["status"] ?? "Present") === "Present"
                                        ? "selected" : "" ?>>Present</option>
                                <option value="Absent"
                                    <?= ($s["status"] ?? "") === "Absent"
                                        ? "selected" : "" ?>>Absent</option>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <?php if ($students): ?>
                <button type="submit">Save Attendance</button>
            <?php endif; ?>
        </form>
    </section>
    <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>