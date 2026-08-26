<template>
    <Teleport to="body">
        <div class="modal fade" ref="modalRef" tabindex="-1" aria-labelledby="clueCreatorModalLabel">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="clueCreatorModalLabel">Új szó hozzáadása</h5>
                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"
                            @click="closeModal"
                        >
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label mb-1">Meghatározás:</label>
                            <input type="text" class="form-control" v-model="definition" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label mb-1">Megfejtés:</label>
                            <input type="text" class="form-control text-uppercase" v-model="solution" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label mb-1">Témakör (opcionális):</label>
                            <v-select
                                v-model="topics"
                                name="topic-v-select"
                                class="rounded"
                                label="name"
                                :placeholder="'Válassz témakört...'"
                                :options="topicOptions"
                                multiple
                            ></v-select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" @click="closeModal">Mégse</button>
                        <button type="button" class="btn btn-primary" @click="save">Mentés</button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script>
import { Modal } from 'bootstrap';
import { createClue } from '../services/crosswordCreatorApi'

export default {
    name: 'ClueCreatorModal',
    props: {
        topicOptions: {
            type: Array,
            required: true
        },
        selectedTopics: {
            type: Array,
            default: null
        }
    },
    data() {
        return {
            definition: '',
            solution: '',
            topics: [],
            modalInstance: null,
        };
    },
    emits: ['closed', 'clue-created'],
    mounted() {
        if (this.$refs.modalRef) {
            this.modalInstance = new Modal(this.$refs.modalRef)

            // Háttére kattintva, esc-et nyomva is bezáródik a modal
            this.$refs.modalRef.addEventListener('hidden.bs.modal', () => {
                this.resetModal()
                this.$emit('closed')
            });
        }
    },
    watch: {
        selectedTopics: {
            immediate: true,
            handler(newTopics) {
                this.topics = newTopics
            }
        }
    },
    methods: {
        /**
         * Megnyitja a modalt
         */
        showModal() {
            if (this.modalInstance) {
                this.modalInstance.show()
            }
        },
        /**
         * Bezárja a modalt
         */
        closeModal() {
            if (this.modalInstance) {
                this.modalInstance.hide()
            }
        },
        /**
         * Visszaállítja a modal mezőit az alapértelmezett értékekre.
         * Meghívódik, amikor a modal bezáródik.
         */
        resetModal() {
            this.definition = ''
            this.solution = ''
            this.topics = this.selectedTopics || []
        },
        /**
         * Leellenőrzi, hogy a meghatározás és a megfejtés mezők érvényesek-e a megadott szabályok szerint.
         * 
         * @return {boolean} - true, ha érvényesek, false, ha nem
         */
        isClueValid() {
            if (this.definition.trim() === '' || this.solution.trim() === '') {
                this.$notify({
                    type: 'error',
                    title: 'Hiba',
                    text: 'A meghatározás és/vagy a megfejtés mezőt nem hagyhatod üresen.',
                })

                return false
            }

            // Regex a megfejtéshez: csak a magyar ábécé betűi, szóközök, számok és speciális karakterek nélkül
            const solutionRegex = /^[a-zA-ZáÁéÉíÍóÓöÖőŐúÚüÜűŰ]+$/

            if (!solutionRegex.test(this.solution)) {
                this.$notify({
                    type: 'error',
                    title: 'Hiba',
                    text: 'A megfejtés mező csak a magyar ábécé betűit tartalmazhatja, szóközök, számok, és speciális karakterek nélkül.',
                })

                return false
            }

            // Regex a meghatározáshoz: csak a magyar ábécé betűi, számok, szóközök és bizonyos speciális karakterek
            const definitionRegex = /^[a-zA-ZáÁéÉíÍóÓöÖőŐúÚüÜűŰ0-9\s.,;:!?'"()\-]+$/

            if (!definitionRegex.test(this.definition)) {
                this.$notify({
                    type: 'error',
                    title: 'Hiba',
                    text: 'A meghatározás mező csak a magyar ábécé betűit, számokat, szóközöket és bizonyos speciális karaktereket tartalmazhat.',
                })

                return false
            }

            if (this.definition.length > 50) {
                this.$notify({
                    type: 'error',
                    title: 'Hiba',
                    text: 'A meghatározás nem lehet hosszabb 50 karakternél.',
                })

                return false
            }

            if (this.solution.length > 20) {
                this.$notify({
                    type: 'error',
                    title: 'Hiba',
                    text: 'A megfejtés nem lehet hosszabb 20 karakternél.',
                })

                return false
            }

            if (this.definition.length < 5) {
                this.$notify({
                    type: 'error',
                    title: 'Hiba',
                    text: 'A meghatározás nem lehet rövidebb 5 karakternél.',
                })

                return false
            }

            return true
        },
        /**
         * Leellenőrzi a mezők érvényességét, majd ha minden rendben van, értesítést küld a sikeres mentésről.
         * 
         * @return {Promise<void>}
         * @emits closed - A modal bezáródik a mentés után
         */
        async save() {
            if (!this.isClueValid()) {
                return
            }
            
            const newClue = {
                definition: this.definition,
                solution: this.solution.toUpperCase(),
                topic_ids: this.topics?.map(topic => topic.id) || [],
            }

            this.$notify({
                type: 'success',
                title: 'Mentés...',
                text: 'A szó mentése folyamatban van.',
            })

            try {
                const createdClue = await createClue(newClue)

                this.$notify({
                    type: 'success',
                    title: 'Sikeres mentés',
                    text: 'A szó sikeresen elmentve.',
                })

                this.$emit('clue-created')
                this.closeModal()

            } catch (error) {
                console.error('Hiba a szó mentésekor:', error)

                this.$notify({
                    type: 'error',
                    title: 'Hiba',
                    text: 'Hiba történt a szó mentésekor.',
                })
            }
        },
    }
}
</script>