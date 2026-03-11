<?php
//TO UPDATE BACKEND AND STATIC TEXTS
?>

<!-- ── About GoSort ── -->
<div class="section-block">
    <div class="section-label">About GoSort</div>
    <div class="inner-card mb-3">
        <div class="d-flex align-items-start gap-3 mb-3">
            <div style="width:42px;height:42px;background:#e8f5e1;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-info-circle" style="font-size:1.2rem;color:var(--primary-green);"></i>
            </div>
            <div>
                <div style="font-size:0.92rem;font-weight:700;color:var(--dark-gray);">GoSort System</div>
                <div style="font-size:0.75rem;color:var(--medium-gray);">Intelligent Waste Sorting Management</div>
            </div>
        </div>

        <p style="font-size:0.82rem;color:var(--medium-gray);line-height:1.75;margin-bottom:0.65rem;">
            <strong style="color:var(--primary-green);">GoSort</strong> is an intelligent waste management system designed to automate and optimize the sorting process.
            GoSort helps organizations reduce waste, improve recycling rates, and contribute to a more sustainable future.
        </p>
        <p style="font-size:0.82rem;color:var(--medium-gray);line-height:1.75;margin-bottom:1.25rem;">
            Our system accurately categorizes waste into biodegradable, non-biodegradable, hazardous, and mixed categories,
            ensuring proper disposal and maximizing resource recovery. With real-time monitoring and comprehensive analytics,
            you gain complete visibility into your waste management operations.
        </p>

        <!-- Feature list -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.75rem;">
            <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.85rem;background:linear-gradient(135deg,#f8fdf6,#f0fdf4);border:1px solid #d4e8d4;border-radius:10px;">
                <div style="width:32px;height:32px;background:#e8f5e1;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-cpu" style="color:var(--primary-green);font-size:0.95rem;"></i>
                </div>
                <div>
                    <div style="font-size:0.78rem;font-weight:700;color:var(--dark-gray);margin-bottom:0.15rem;">Automated Trash Sorting</div>
                    <div style="font-size:0.7rem;color:var(--medium-gray);">Intelligent classification for accurate waste separation</div>
                </div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.85rem;background:linear-gradient(135deg,#f8fdf6,#f0fdf4);border:1px solid #d4e8d4;border-radius:10px;">
                <div style="width:32px;height:32px;background:#e8f5e1;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-clock-history" style="color:var(--primary-green);font-size:0.95rem;"></i>
                </div>
                <div>
                    <div style="font-size:0.78rem;font-weight:700;color:var(--dark-gray);margin-bottom:0.15rem;">Real-Time Monitoring</div>
                    <div style="font-size:0.7rem;color:var(--medium-gray);">Live tracking of all sorting operations</div>
                </div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.85rem;background:linear-gradient(135deg,#f8fdf6,#f0fdf4);border:1px solid #d4e8d4;border-radius:10px;">
                <div style="width:32px;height:32px;background:#e8f5e1;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-bar-chart" style="color:var(--primary-green);font-size:0.95rem;"></i>
                </div>
                <div>
                    <div style="font-size:0.78rem;font-weight:700;color:var(--dark-gray);margin-bottom:0.15rem;">Advanced Analytics</div>
                    <div style="font-size:0.7rem;color:var(--medium-gray);">Comprehensive insights and performance metrics</div>
                </div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.85rem;background:linear-gradient(135deg,#f8fdf6,#f0fdf4);border:1px solid #d4e8d4;border-radius:10px;">
                <div style="width:32px;height:32px;background:#e8f5e1;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-shield-check" style="color:var(--primary-green);font-size:0.95rem;"></i>
                </div>
                <div>
                    <div style="font-size:0.78rem;font-weight:700;color:var(--dark-gray);margin-bottom:0.15rem;">Safety Compliance</div>
                    <div style="font-size:0.7rem;color:var(--medium-gray);">Proper handling of hazardous materials</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Teams row -->
    <div class="row g-3">

        <!-- Custodial Team -->
        <div class="col-lg-6">
            <div class="inner-card">
                <div class="inner-card-header">
                    <div class="inner-card-title">Custodial Team</div>
                    <i class="bi bi-people" style="color:var(--primary-green);font-size:1rem;"></i>
                </div>
                <?php
                $custodians = [
                    ['Marlon Lagramada',      'Utility Head'],
                    ['Digna De Cagayunan',    'Utility Member'],
                    ['Janice Ison',           'Utility Member'],
                    ['Mira Luna Villadares',  'Utility Member'],
                ];
                foreach ($custodians as $m): ?>
                <div style="display:flex;align-items:center;gap:0.75rem;padding:0.6rem 0.5rem;border-bottom:1px solid #f3f4f6;">
                    <img src="images/icons/team.svg" alt="Custodian"
                        style="width:34px;height:34px;border-radius:50%;background:#e8f5e1;padding:4px;flex-shrink:0;">
                    <div>
                        <div style="font-size:0.8rem;font-weight:700;color:var(--dark-gray);"><?php echo $m[0]; ?></div>
                        <div style="font-size:0.7rem;color:var(--medium-gray);"><?php echo $m[1]; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Developers -->
        <div class="col-lg-6">
            <div class="inner-card">
                <div class="inner-card-header">
                    <div class="inner-card-title">Developers Behind GoSort</div>
                    <i class="bi bi-code-slash" style="color:var(--primary-green);font-size:1rem;"></i>
                </div>
                <?php
                $devs = [
                    ['Gwyneth Beatrice Landero',  'Project Manager, UI/UX Designer'],
                    ['Michael Josh Bargabino',     'Mobile App Developer'],
                    ['Miguel Roberto Sta. Maria',  'Web Developer'],
                    ['Diosdado Tempra Jr.',        'Tester, Researcher'],
                ];
                foreach ($devs as $d): ?>
                <div style="display:flex;align-items:center;gap:0.75rem;padding:0.6rem 0.5rem;border-bottom:1px solid #f3f4f6;">
                    <div style="width:34px;height:34px;background:#e8f5e1;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-person-circle" style="font-size:1rem;color:var(--primary-green);"></i>
                    </div>
                    <div>
                        <div style="font-size:0.8rem;font-weight:700;color:var(--dark-gray);"><?php echo $d[0]; ?></div>
                        <div style="font-size:0.7rem;color:var(--medium-gray);"><?php echo $d[1]; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>