                                <div class="xyz-native-sim__grid">
                                    <label class="xyz-native-sim__control">
                                        <span>espace</span>
                                        <select data-spatial-native-sim-select="space">
                                            <option value="window">fenêtre</option>
                                            <option value="volume">volume</option>
                                            <option value="shared">shared</option>
                                            <option value="full">full</option>
                                        </select>
                                        <output data-spatial-native-sim-output="space">shared</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>passthrough</span>
                                        <select data-spatial-native-sim-select="passthrough">
                                            <option value="none">none</option>
                                            <option value="mixed">mixed</option>
                                            <option value="full">full</option>
                                            <option value="portal">portal</option>
                                            <option value="progressive">progressive</option>
                                        </select>
                                        <output data-spatial-native-sim-output="passthrough">mixed</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>lumière</span>
                                        <input type="range" min="0" max="100" step="1" value="68" data-spatial-native-sim-range="lightLevel">
                                        <output data-spatial-native-sim-output="lightLevel">68%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>contraste</span>
                                        <input type="range" min="0" max="100" step="1" value="24" data-spatial-native-sim-range="lightContrast">
                                        <output data-spatial-native-sim-output="lightContrast">24%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>lumière X</span>
                                        <input type="range" min="-100" max="100" step="1" value="16" data-spatial-native-sim-range="lightDirectionX">
                                        <output data-spatial-native-sim-output="lightDirectionX">+16%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>lumière Y</span>
                                        <input type="range" min="-100" max="100" step="1" value="-8" data-spatial-native-sim-range="lightDirectionY">
                                        <output data-spatial-native-sim-output="lightDirectionY">-8%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>tête</span>
                                        <input type="range" min="0" max="100" step="1" value="16" data-spatial-native-sim-range="headSpeed">
                                        <output data-spatial-native-sim-output="headSpeed">16%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>ancrage</span>
                                        <input type="range" min="0" max="100" step="1" value="84" data-spatial-native-sim-range="anchorStability">
                                        <output data-spatial-native-sim-output="anchorStability">84%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>ancres</span>
                                        <input type="range" min="0" max="12" step="1" value="5" data-spatial-native-sim-range="anchorsTracked">
                                        <output data-spatial-native-sim-output="anchorsTracked">5</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>plans</span>
                                        <input type="range" min="0" max="12" step="1" value="3" data-spatial-native-sim-range="planesTracked">
                                        <output data-spatial-native-sim-output="planesTracked">3</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>mains actives</span>
                                        <input type="range" min="0" max="2" step="1" value="2" data-spatial-native-sim-range="activeHands">
                                        <output data-spatial-native-sim-output="activeHands">2</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>prise gauche</span>
                                        <input type="range" min="0" max="100" step="1" value="22" data-spatial-native-sim-range="leftPinch">
                                        <output data-spatial-native-sim-output="leftPinch">22%</output>
                                    </label>
                                    <label class="xyz-native-sim__control">
                                        <span>prise droite</span>
                                        <input type="range" min="0" max="100" step="1" value="42" data-spatial-native-sim-range="rightPinch">
                                        <output data-spatial-native-sim-output="rightPinch">42%</output>
                                    </label>
                                </div>
