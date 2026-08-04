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
                            <input type="text" class="form-control" v-model="solution" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label mb-1">Téma (opcionális):</label>
                            <input type="text" class="form-control" v-model="theme" />
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

export default {
    name: 'ClueCreatorModal',
    data() {
        return {
            definition: '',
            solution: '',
            theme: '',
            modalInstance: null,
        };
    },
    emits: ['closed'],
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
            this.theme = ''
        },
        /**
         * Leellenőrzi, hogy a meghatározás és a megfejtés mezők érvényesek-e a megadott szabályok szerint.
         * 
         * @return {boolean} - true, ha érvényesek, false, ha nem
         */
        isClueValid() {
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

            if (this.definition.trim() === '' || this.solution.trim() === '') {
                this.$notify({
                    type: 'error',
                    title: 'Hiba',
                    text: 'A meghatározás és/vagy a megfejtés mezőt nem hagyhatod üresen.',
                })

                return false
            }

            if (this.definition.length > 50 || this.solution.length > 50) {
                this.$notify({
                    type: 'error',
                    title: 'Hiba',
                    text: 'A meghatározás és/vagy a megfejtés mezők nem lehetnek hosszabbak 50 karakternél.',
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
                solution: this.solution,
                theme: this.theme,
            }

            this.$notify({
                type: 'success',
                title: 'Sikeres mentés',
                text: 'A szó sikeresen hozzáadva.',
            })
        },
    }
}
</script>