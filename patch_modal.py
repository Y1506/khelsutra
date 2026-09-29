import re

with open('backend/resources/views/operations/accommodation/index.blade.php', 'r') as f:
    content = f.read()

# I will find "</form>" inside the accommodationModal and replace it with </form> + modal-footer + closing divs

footer = """
                </form>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveAccommodation()">Save Accommodation</button>
            </div>
        </div>
    </div>
</div>
"""

# The problem is that there might be extra closing </div>s after </form> that I need to replace.
# Let's see what comes after </form>

