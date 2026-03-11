<?php
//TO UPDATE BACKEND AND STATIC TEXTS
?>

<!-- ── Help & Support ── -->
<div class="section-block">
    <div class="section-label">Help &amp; Support</div>

    <!-- Intro -->
    <div class="inner-card mb-3">
        <div class="d-flex align-items-center gap-3 mb-2">
            <div style="width:42px;height:42px;background:#e8f5e1;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-question-circle" style="font-size:1.2rem;color:var(--primary-green);"></i>
            </div>
            <div>
                <div style="font-size:0.92rem;font-weight:700;color:var(--dark-gray);">GoSort Help &amp; Support Center</div>
                <div style="font-size:0.75rem;color:var(--medium-gray);">Find answers, guides, and contact options below</div>
            </div>
        </div>
        <p style="font-size:0.82rem;color:var(--medium-gray);line-height:1.75;margin:0;">
            Welcome to the GoSort Help &amp; Support Center. Here you can find answers to common questions,
            troubleshooting steps, and ways to contact our support team for assistance with your GoSort device or system.
        </p>
    </div>

    <!-- Quick Start Guide -->
    <div class="inner-card mb-3">
        <div class="inner-card-header">
            <div class="inner-card-title"><i class="bi bi-lightning-charge-fill me-2" style="color:var(--primary-green);"></i>Quick Start Guide</div>
        </div>
        <div class="row g-2">
            <?php
            $steps = [
                "Power on the GoSort device and ensure it's connected to electricity.",
                "Connect to Wi-Fi or ensure your system is linked to the GoSort dashboard.",
                "Place waste items one at a time on the sorting tray.",
                "View sorting results on the dashboard or indicator panel.",
                "Empty bins regularly to maintain efficiency.",
            ];
            foreach ($steps as $i => $step): ?>
            <div class="col-md-6 col-lg-4">
                <div style="display:flex;align-items:flex-start;gap:0.65rem;padding:0.65rem;background:#f8fdf6;border:1px solid #e8f5e1;border-radius:8px;height:100%;">
                    <div style="width:24px;height:24px;min-width:24px;background:linear-gradient(135deg,var(--mid-green),var(--primary-green));border-radius:50%;display:flex;align-items:center;justify-content:center;margin-top:1px;">
                        <span style="font-size:0.65rem;font-weight:700;color:#fff;"><?php echo $i+1; ?></span>
                    </div>
                    <div style="font-size:0.8rem;color:var(--dark-gray);line-height:1.6;"><?php echo $step; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- FAQs + Troubleshooting -->
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="inner-card h-100">
                <div class="inner-card-header">
                    <div class="inner-card-title"><i class="bi bi-chat-dots-fill me-2" style="color:var(--primary-green);"></i>Frequently Asked Questions</div>
                </div>
                <?php
                $faqs = [
                    ["Why is my GoSort device not detecting the waste?",
                     "Check if the sensor lens is clean and unobstructed. Wipe gently with a dry cloth. Also, make sure the device is powered and connected."],
                    ["What kinds of materials can GoSort identify?",
                     "GoSort can automatically identify biodegradable, non-biodegradable, hazardous, and general waste."],
                    ["How often should I clean the device?",
                     "It's recommended to clean the sorting tray and bins once a day to ensure smooth sensor detection and operation."],
                    ["How do I update the GoSort software?",
                     "Updates are handled automatically when the system connects to the internet."],
                ];
                foreach ($faqs as $idx => $faq): ?>
                <div class="support-faq-item" id="faq-item-<?php echo $idx; ?>">
                    <button class="support-faq-btn" onclick="toggleFaq(<?php echo $idx; ?>)">
                        <span><?php echo $faq[0]; ?></span>
                        <i class="bi bi-chevron-down support-faq-icon"></i>
                    </button>
                    <div class="support-faq-body">
                        <p style="margin:0;"><?php echo $faq[1]; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="inner-card h-100">
                <div class="inner-card-header">
                    <div class="inner-card-title"><i class="bi bi-tools me-2" style="color:var(--primary-green);"></i>Troubleshooting Guide</div>
                </div>
                <div style="overflow-x:auto;">
                    <table style="width:100%;font-size:0.8rem;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f0fdf4;">
                                <th style="padding:0.6rem 0.75rem;font-weight:700;color:var(--primary-green);border-bottom:2px solid #c8e6c9;">Issue</th>
                                <th style="padding:0.6rem 0.75rem;font-weight:700;color:var(--primary-green);border-bottom:2px solid #c8e6c9;">Possible Cause</th>
                                <th style="padding:0.6rem 0.75rem;font-weight:700;color:var(--primary-green);border-bottom:2px solid #c8e6c9;">Solution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $issues = [
                                ["Device not powering on", "Loose power cable or faulty outlet",  "Check plug and outlet connection. Try another socket."],
                                ["Sensor misreads item",   "Dirty or blocked sensor",             "Clean the sensor lens with a soft cloth."],
                                ["Bins not moving",        "Internal obstruction",                "Turn off device, clear blockage, and restart."],
                            ];
                            foreach ($issues as $i => $row):
                                $bg = $i % 2 === 0 ? '#fff' : '#fafafa';
                            ?>
                            <tr style="background:<?php echo $bg; ?>;">
                                <td style="padding:0.6rem 0.75rem;border-bottom:1px solid #f3f4f6;font-weight:600;color:var(--dark-gray);"><?php echo $row[0]; ?></td>
                                <td style="padding:0.6rem 0.75rem;border-bottom:1px solid #f3f4f6;color:var(--medium-gray);"><?php echo $row[1]; ?></td>
                                <td style="padding:0.6rem 0.75rem;border-bottom:1px solid #f3f4f6;color:var(--medium-gray);"><?php echo $row[2]; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Support — full width -->
    <div class="inner-card">
        <div class="inner-card-header">
            <div class="inner-card-title"><i class="bi bi-headset me-2" style="color:var(--primary-green);"></i>Contact Technical Support</div>
        </div>
        <div class="row g-3">
            <?php
            $contacts = [
                ["bi-envelope-fill",  "Email", "gosort.support@gmail.com"],
                ["bi-telephone-fill", "Phone", "(09) 206897957"],
                ["bi-clock-fill",     "Hours", "Monday–Friday, 6:00 AM – 6:00 PM"],
            ];
            foreach ($contacts as $c): ?>
            <div class="col-md-4">
                <div style="display:flex;align-items:center;gap:0.75rem;padding:0.85rem;background:#f8fdf6;border:1px solid #e8f5e1;border-radius:10px;">
                    <div style="width:36px;height:36px;background:#e8f5e1;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi <?php echo $c[0]; ?>" style="color:var(--primary-green);font-size:0.9rem;"></i>
                    </div>
                    <div>
                        <div style="font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--medium-gray);"><?php echo $c[1]; ?></div>
                        <div style="font-size:0.82rem;font-weight:600;color:var(--dark-gray);"><?php echo $c[2]; ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<style>
.support-faq-item {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    margin-bottom: 0.5rem;
    overflow: hidden;
    transition: border-color 0.2s;
}
.support-faq-item.open { border-color: var(--mid-green); }
.support-faq-btn {
    width: 100%;
    text-align: left;
    padding: 0.7rem 0.85rem;
    background: none;
    border: none;
    font-family: 'Poppins', sans-serif;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--dark-gray);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: color 0.2s;
}
.support-faq-btn:hover { color: var(--primary-green); }
.support-faq-item.open .support-faq-btn { color: var(--primary-green); }
.support-faq-icon { flex-shrink:0; font-size:0.75rem; transition: transform 0.25s ease; }
.support-faq-item.open .support-faq-icon { transform: rotate(180deg); }
.support-faq-body {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease, padding 0.2s ease;
    padding: 0 0.85rem;
    font-size: 0.8rem;
    color: var(--medium-gray);
    line-height: 1.7;
}
.support-faq-item.open .support-faq-body {
    max-height: 200px;
    padding: 0 0.85rem 0.75rem;
}
</style>

<script>
function toggleFaq(idx) {
    const item = document.getElementById('faq-item-' + idx);
    const isOpen = item.classList.contains('open');
    document.querySelectorAll('.support-faq-item').forEach(el => el.classList.remove('open'));
    if (!isOpen) item.classList.add('open');
}
</script>