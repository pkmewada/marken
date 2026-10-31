<?php
// Shared by admin/ and api/ for careers, service centers and events.
// Kept out of config.php: that file is per-server (gitignored), these are not.

// Resumes are personal data: stored outside uploads/ in a folder denied over HTTP
// (storage/.htaccess) and only served to a logged-in admin by admin/resume.php.
define('RESUME_DIR', ROOT_DIR . '/storage/resumes/');
define('MAX_RESUME_BYTES', 5 * 1024 * 1024);
const RESUME_TYPES = [
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
];

const EMPLOYMENT_TYPES = [
    'full-time'  => 'Full-time',
    'part-time'  => 'Part-time',
    'contract'   => 'Contract',
    'internship' => 'Internship',
];

const APPLICATION_STATUSES = [
    'new'          => 'New',
    'under-review' => 'Under Review',
    'shortlisted'  => 'Shortlisted',
    'rejected'     => 'Rejected',
    'selected'     => 'Selected',
];

// Service center filter options ticked per center in the admin.
const SERVICE_TYPES = ['Installation', 'Repair & Maintenance', 'AMC / CMC', 'Spare Parts', 'Calibration'];

const EVENT_STATUSES = [
    'auto'      => 'Automatic (from dates)',
    'upcoming'  => 'Upcoming',
    'ongoing'   => 'Ongoing',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

// An admin override wins; otherwise the DB clock decides from the dates (end date blank = one-day event).
const EVENT_PHASE_SQL = "CASE WHEN e.eventStatus <> 'auto' THEN e.eventStatus
    WHEN e.startDate > CURDATE() THEN 'upcoming'
    WHEN COALESCE(e.endDate, e.startDate) >= CURDATE() THEN 'ongoing'
    ELSE 'completed' END";

// A job is open while published and its deadline (if any) has not passed.
const JOB_OPEN_SQL = "j.status = 'published' AND (j.deadline IS NULL OR j.deadline >= CURDATE())";
