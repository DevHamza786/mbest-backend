<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Student Enrolled</title>
</head>
<body>
    <h2>New Student Enrolled in Your Class</h2>
    
    <p>Hello {{ $class->tutor->user->name ?? 'Tutor' }},</p>
    
    <p>A new student has been enrolled in your class:</p>
    
    <ul>
        <li><strong>Student Name:</strong> {{ $student->user->name }}</li>
        <li><strong>Enrollment ID:</strong> {{ $student->enrollment_id }}</li>
        <li><strong>Class:</strong> {{ $class->name }}</li>
        <li><strong>Grade:</strong> {{ $student->grade ?? 'N/A' }}</li>
        <li><strong>School:</strong> {{ $student->school ?? 'N/A' }}</li>
    </ul>
    
    <p>Please log in to your dashboard to view the student's profile and manage their enrollment.</p>
    
    <p>Best regards,<br>MBEST LMS</p>
</body>
</html>
