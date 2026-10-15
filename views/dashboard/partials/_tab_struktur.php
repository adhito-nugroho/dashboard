
            <!-- Tab 3: Struktur Anggaran -->
            <div class="tab-pane fade" id="tab-struktur" role="tabpanel" aria-labelledby="tab-struktur-btn">
                <!-- Hierarchical Budget Structure -->
    <?php if (!empty($hierarchicalData)): ?>
    <div class="row mb-4" id="section-struktur">
        <div class="col-12 animate-fade-in-up delay-6">
            <div class="card rounded-4">
                <div class="section-header">
                    <div>
                        <h5 class="section-title"><i class="bi bi-diagram-3 me-2" style="color:var(--primary);"></i>Struktur Anggaran & Realisasi</h5>
                        <p class="section-subtitle">Hierarki program hingga rekening belanja</p>
                    </div>
                </div>
                <div class="card-body" style="padding:1.5rem; overflow-x:auto;">

                    <?php
                    function renderTreeNodes($nodes, $level = 0) {
                        $badges = [
                            0 => ['name'=>'Seksi','class'=>'badge-seksi'], 
                            1 => ['name'=>'Program','class'=>'badge-program'], 
                            2 => ['name'=>'Kegiatan','class'=>'badge-kegiatan'], 
                            3 => ['name'=>'Sub Kegiatan','class'=>'badge-sub'], 
                            4 => ['name'=>'Belanja','class'=>'badge-belanja']
                        ];
                        $badge = $badges[$level] ?? $badges[4];
                        
                        foreach ($nodes as $node) {
                            $pct = $node['pagu'] > 0 ? ($node['realisasi'] / $node['pagu']) * 100 : 0;
                            $paguStr = number_format($node['pagu'], 0, ',', '.');
                            $realStr = number_format($node['realisasi'], 0, ',', '.');
                            
                            $hasChildren = !empty($node['children']);
                            $itemClass = 'h-item' . ($hasChildren ? ' has-children' : '');
                            $nodeClass = 'h-node pt-0 pb-0' . ($hasChildren ? ' tree-collapsed' : '');
                            $attrs = $hasChildren ? ' role="button" tabindex="0" aria-expanded="false" onclick="toggleTreeNode(this)" onkeydown="handleTreeKey(event, this)"' : '';
                            
                            echo '<div class="' . $nodeClass . '">';
                            if ($level > 0) echo '<div class="h-line-h"></div>';
                            
                            echo '<div class="' . $itemClass . '"' . $attrs . '>';
                            // Left Section (Toggle, Dot, Title, Code)
                            echo '<div class="h-item-left align-items-center">';
                            if ($hasChildren) {
                                echo '<div class="h-toggle"><i class="bi bi-chevron-down"></i></div>';
                            } else {
                                echo '<div style="width: 24px; margin-right: 0.25rem; flex-shrink: 0;"></div>';
                            }
                            
                            if ($level > 0) echo '<div class="h-dot" style="margin-top:0; background: '.($level==4?'var(--gray-400)':'var(--primary-light)').';"></div>';
                            else echo '<div class="h-dot" style="margin-top:0; background: var(--primary);"></div>';
                            
                            echo '<div class="ms-1" style="flex:1;">';
                            echo '<div class="h-title" style="'.($level==4?'font-weight:600;color:var(--gray-700);':'').'">' . htmlspecialchars($node['nama']) . '</div>';
                            echo '<div class="h-code"><span class="h-badge ' . $badge['class'] . '">' . $badge['name'] . '</span> Kode: ' . $node['kode'] . '</div>';
                            echo '</div>';
                            echo '</div>'; // End left
                            
                            // Center Section (Anggaran)
                            echo '<div class="h-card-sm ms-3 d-none d-lg-flex">';
                            echo '<div class="h-icon-box icon-box-anggaran"><i class="bi bi-calculator"></i></div>';
                            echo '<div><div class="h-val-title">Rp ' . $paguStr . '</div><div class="h-val-sub">Anggaran</div></div>';
                            echo '</div>';
                            
                            // Center Section (% Realisasi)
                            echo '<div class="h-progress-wrap ms-3 d-none d-md-flex">';
                            echo '<span style="font-size:var(--fs-base); font-weight:700; color:var(--primary);">' . number_format($pct, 2) . '%</span>';
                            echo '<div class="h-progress-bar"><div class="h-progress-fill progress-fill-safe" style="width:' . min($pct, 100) . '%;"></div></div>';
                            echo '</div>';
                            
                            // Right Section (Realisasi)
                            echo '<div class="h-card-sm ms-3 ps-3 d-none d-sm-flex" style="border-left: 1px dashed var(--gray-200);">';
                            echo '<div class="h-icon-box icon-box-realisasi"><i class="bi bi-wallet2"></i></div>';
                            echo '<div><div class="h-val-title">Rp ' . $realStr . '</div><div class="h-val-sub">Realisasi Riil</div></div>';
                            echo '</div>';
                            
                            echo '</div>'; // End item
                            
                            if ($hasChildren) {
                                echo '<div class="h-tree">';
                                renderTreeNodes($node['children'], $level + 1);
                                echo '</div>';
                            }
                            
                            echo '</div>'; // End node
                        }
                    }
                    ?>

                    <div class="h-tree" style="padding-left:0;">
                        <?php renderTreeNodes($hierarchicalData); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
            </div>
