<?php
// AJAX: Member AI Coaching
require_once dirname(__DIR__) . '/functions.php';
header('Content-Type: application/json');
session_start_safe();

if (!is_logged_in()) {
    echo json_encode(['tips' => 'Please log in to get coaching tips.']);
    exit;
}

$member   = current_member();
$kop_id   = $member['koperasi_id'];
$listings = get_sellers(['koperasi_id' => $kop_id]);
$requests = get_requests(['member_kop_id' => $kop_id]);

$listing_summary = $listings
    ? implode('; ', array_map(fn($s) => "{$s['service_title']} ({$s['category']}, {$s['area']})", $listings))
    : 'No listings yet';

$request_summary = $requests
    ? implode('; ', array_map(fn($r) => "{$r['category']} in {$r['location']} ({$r['status']})", $requests))
    : 'No requests yet';

$prompt = "You are coaching a Koponix member named {$member['name']} (ID: {$kop_id}).\n\n"
    . "Their service listings: {$listing_summary}\n"
    . "Their buyer requests: {$request_summary}\n\n"
    . "Give exactly 3 short, practical, actionable tips to help this member earn more or engage better "
    . "on the Koponix platform. Number each tip. Keep each tip to 1-2 sentences.";

$tips = claude_api([['role'=>'user','content'=>$prompt]], KOPONIX_SYSTEM_PROMPT, 350, CLAUDE_MODEL);
echo json_encode(['tips' => trim($tips)]);
