                                            <details class="xyz-archi-panel xyz-archi-panel--nested xyz-archi-panel--subtle" id="xyz-panel-daw-transport" data-xyz-archi-panel data-xyz-archi-group="music-desk" data-xyz-archi-default-open="1" open>
                                                <summary class="xyz-archi-panel__summary">
                                                    <span class="summary-label">session</span>
                                                    <strong>Transport &amp; capture</strong>
                                                    <span class="xyz-archi-panel__meta">lecture, rec, stems, projet</span>
                                                </summary>
                                                <div class="xyz-archi-panel__content">
                                                    <div class="xyz-music-desk__transport">
                                                        <div class="xyz-music-desk__transport-head">
                                                            <span class="summary-label">session</span>
                                                            <strong data-xyz-daw-status>prête</strong>
                                                        </div>
                                                        <div class="xyz-music-desk__transport-actions" role="group" aria-label="Transport musical">
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-play>lecture locale</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-stop>stop</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button xyz-music-desk__transport-button--record" data-xyz-daw-record>rec audio</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button xyz-music-desk__transport-button--performance" data-xyz-daw-record-performance>rec perf</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-export-stems>export stems</button>
                                                            <button type="button" class="ghost-link xyz-music-desk__transport-button" data-xyz-daw-export-project>export projet</button>
                                                        </div>
                                                        <div class="xyz-music-desk__transport-grid">
                                                            <label class="xyz-music-desk__field">
                                                                <span>bpm</span>
                                                                <input type="range" min="60" max="168" step="1" value="96" data-xyz-daw-bpm-input>
                                                                <strong data-xyz-daw-bpm-readout>96</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>swing</span>
                                                                <input type="range" min="0" max="40" step="1" value="12" data-xyz-daw-swing-input>
                                                                <strong data-xyz-daw-swing-readout>12%</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>humanize</span>
                                                                <input type="range" min="0" max="36" step="1" value="14" data-xyz-daw-humanize-input>
                                                                <strong data-xyz-daw-humanize-readout>14%</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>microtiming</span>
                                                                <input type="range" min="0" max="24" step="1" value="9" data-xyz-daw-microtiming-input>
                                                                <strong data-xyz-daw-microtiming-readout>9 ms</strong>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>boucle</span>
                                                                <select data-xyz-daw-loop-select>
                                                                    <option value="2">2 mesures</option>
                                                                    <option value="4" selected>4 mesures</option>
                                                                    <option value="8">8 mesures</option>
                                                                    <option value="16">16 mesures</option>
                                                                </select>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>quantize</span>
                                                                <select data-xyz-daw-quantize-select>
                                                                    <option value="1/4">1/4</option>
                                                                    <option value="1/8" selected>1/8</option>
                                                                    <option value="1/16">1/16</option>
                                                                </select>
                                                            </label>
                                                            <label class="xyz-music-desk__field">
                                                                <span>count-in</span>
                                                                <select data-xyz-daw-countin-select>
                                                                    <option value="0">off</option>
                                                                    <option value="1" selected>1 mesure</option>
                                                                    <option value="2">2 mesures</option>
                                                                </select>
                                                            </label>
                                                            <p class="xyz-music-desk__clock" data-xyz-daw-clock>boucle 4 mesures · mesure 1 · temps 1</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>
