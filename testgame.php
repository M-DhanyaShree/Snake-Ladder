<!DOCTYPE html>
<html>
<head>
  <title>JS Test</title>
</head>
<body>

  <form id="form1">
    <label>Number of Players:</label>
    <select id="cnt">
      <option value="">-- Select --</option>
      <option value="2">2</option>
      <option value="3">3</option>
    </select>
    <button type="button" id="next-btn">Next</button>
  </form>

  <form id="form2" style="display:none"></form>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      document.getElementById('next-btn').addEventListener('click', function () {
        let count = document.getElementById("cnt").value;
        if (!count) {
          alert("Select player count");
          return;
        }

        document.getElementById("form1").style.display = "none";
        let form2 = document.getElementById("form2");
        form2.style.display = "block";

        form2.innerHTML = "";
        for (let i = 0; i < count; i++) {
          form2.innerHTML += `
            <p>Player ${i + 1} Name: <input type="text" name="p${i}"></p>
          `;
        }

        form2.innerHTML += `<button type="submit">Submit</button>`;
      });
    });
  </script>

</body>
</html>
